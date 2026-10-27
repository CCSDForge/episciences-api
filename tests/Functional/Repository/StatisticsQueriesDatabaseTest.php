<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Repository\PaperLogRepository;
use App\Service\Stats;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineExtensions\Query\Mysql\TimestampDiff;
use DoctrineExtensions\Query\Mysql\Year;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The statistics queries give the same results as the reference SQL queries they replaced (non-regression),
 * against a real MySQL database, for all the combinations of their filters.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS, PAPER_LOG and REVIEW tables: it is skipped if one of them already exists,
 * so that it never touches real data.
 */
class StatisticsQueriesDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;
    private const int DISABLED_RVID = 3;
    private const string START_AFTER_DATE = '2022-01-01';

    private EntityManagerInterface $entityManager;
    private PaperLogRepository $paperLogRepository;

    /** @var list<ClassMetadata<object>> */
    private array $metadata = [];

    private bool $tablesCreated = false;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL') ?: ($_SERVER['TEST_DATABASE_URL'] ?? null);

        if (!$url) {
            $this->markTestSkipped('TEST_DATABASE_URL is not set.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->addCustomStringFunction('YEAR', Year::class);
        $config->addCustomStringFunction('TIMESTAMPDIFF', TimestampDiff::class);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $config);

        foreach ([Paper::TABLE, PaperLog::TABLE, Review::TABLE] as $table) {
            if ($connection->createSchemaManager()->tablesExist([$table])) {
                $connection->close();
                $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', $table));
            }
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = [$this->entityManager->getClassMetadata(Paper::class), $this->entityManager->getClassMetadata(PaperLog::class), $this->entityManager->getClassMetadata(Review::class)];

        // Only PAPERS, PAPER_LOG and REVIEW are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tablesCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->paperLogRepository = new PaperLogRepository($registry, new NullLogger());

        $this->insertData();
    }

    protected function tearDown(): void
    {
        if ($this->tablesCreated) {
            new SchemaTool($this->entityManager)->dropSchema($this->metadata);
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    /**
     * The delay of each article: the same rows as the reference SQL query.
     */
    public function testArticleDelaysMatchTheReferenceQuery(): void
    {
        $withRows = 0;

        foreach ($this->delayFilters() as $label => [$unit, $status, $rvId, $years, $startDate]) {
            $expected = $this->fetch(self::referenceArticleDelaysSql($unit, $status, $startDate, $rvId, $years));
            $actual = $this->paperLogRepository->getArticleDelays($unit, $status, $startDate, $years, $rvId);

            $this->assertSame(self::normalizeArticleDelays($expected), self::normalizeArticleDelays($actual), $label);
            $withRows += $expected === [] ? 0 : 1;
        }

        // the fixtures give rows for most of the filters
        $this->assertGreaterThan(100, $withRows);
    }

    /**
     * The average delays, computed from the delay of each article: the same rows as the reference SQL query
     * (average by journal and year, rounded, with the number of articles).
     */
    public function testAverageDelaysMatchTheReferenceQuery(): void
    {
        foreach ($this->delayFilters() as $label => [$unit, $status, $rvId, $years, $startDate]) {
            $expected = $this->fetch(self::referenceAverageDelaysSql($unit, $status, $startDate, $rvId, $years));
            $actual = Stats::averageDelayRows($this->paperLogRepository->getArticleDelays($unit, $status, $startDate, $years, $rvId) ?? []);

            $this->assertSame(self::normalizeAverageDelays($expected), self::normalizeAverageDelays($actual), $label);
        }
    }

    /**
     * An average of n.5 is rounded half away from zero, as MySQL: (389 + 18) / 2 = 203.5 days, i.e. 204
     */
    public function testAverageDelayIsRoundedAsMySql(): void
    {
        $rows = Stats::averageDelayRows($this->paperLogRepository->getArticleDelays('DAY', Paper::STATUS_STRICTLY_ACCEPTED, null, [2023], self::RVID) ?? []);

        $this->assertSame([[2023, self::RVID, 204.0, 2]], self::normalizeAverageDelays($rows));
    }

    /**
     * The papers given a status the year of their first submission: the same rows as the reference SQL query
     * (without journal, all the journals, as the reference).
     */
    public function testSubmittedSameYearMatchesTheReferenceQuery(): void
    {
        $withRows = 0;

        foreach ([Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_PUBLISHED, Paper::STATUS_REFUSED] as $status) {
            $reference = $this->fetch(self::referenceSubmittedSameYearSql($status));

            foreach ([null, self::RVID, self::OTHER_RVID, self::DISABLED_RVID] as $rvId) {
                // the reference query gives all the journals: the rows of the journal
                $expected = $rvId === null ? $reference : array_values(array_filter($reference, static fn(array $row): bool => (int)$row['rvid'] === $rvId));
                $actual = $this->paperLogRepository->getSubmittedSameYear($status, $rvId);

                $this->assertSame(self::normalizeCounts($expected), self::normalizeCounts($actual), sprintf('status %d, journal %s', $status, $rvId ?? '-'));
                $withRows += $expected === [] ? 0 : 1;
            }
        }

        $this->assertGreaterThan(5, $withRows);
    }

    /**
     * All the combinations of the filters of the delays
     *
     * @return iterable<string, array{0: string, 1: int, 2: int|null, 3: mixed, 4: string|null}>
     */
    private function delayFilters(): iterable
    {
        foreach (['DAY', 'WEEK', 'MONTH'] as $unit) {
            foreach ([Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_PUBLISHED] as $status) {
                foreach ([null, self::RVID, self::OTHER_RVID, self::DISABLED_RVID] as $rvId) {
                    foreach ([null, [2022], [2023, 2024], '2023', 2024] as $years) {
                        foreach ([null, self::START_AFTER_DATE] as $startDate) {
                            $label = sprintf('unit %s, status %d, journal %s, years %s, start %s', $unit, $status, $rvId ?? '-', json_encode($years), $startDate ?? '-');
                            yield $label => [$unit, $status, $rvId, $years, $startDate];
                        }
                    }
                }
            }
        }
    }

    /**
     * Reference: the SQL query of the delay of each article, before its conversion to Doctrine.
     *
     * @param int[]|string|int|null $years valid years only
     */
    private static function referenceArticleDelaysSql(string $unit, int $latestStatus, ?string $startDate, ?int $rvId, array|string|int|null $years): string
    {
        $latestStatuses = $latestStatus === Paper::STATUS_STRICTLY_ACCEPTED ? [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED] : [$latestStatus];

        $sql = "SELECT YEAR(t2.max_date) AS year, t1.RVID AS rvid, TIMESTAMPDIFF($unit, min_date, max_date) AS delay, t1.PAPERID, t1.min_date, t2.max_date FROM (";
        $sql .= 'SELECT pl1.PAPERID, MIN(pl1.DATE) AS min_date, pl1.RVID FROM PAPER_LOG pl1 WHERE pl1.status = ' . Paper::STATUS_SUBMITTED;
        $sql .= $rvId ? " AND pl1.rvid = $rvId" : ' AND pl1.rvid IN (SELECT RVID FROM REVIEW WHERE STATUS = ' . Review::STATUS_ENABLED . ')';
        $sql .= ' GROUP BY pl1.PAPERID, pl1.RVID ) t1 INNER JOIN(';
        $sql .= ' SELECT pl2.PAPERID, MAX(pl2.DATE) AS max_date, pl2.RVID FROM PAPER_LOG pl2 WHERE pl2.status IN (' . implode(', ', $latestStatuses) . ')';
        $sql .= $rvId ? " AND pl2.rvid = $rvId" : '';
        $sql .= ' GROUP BY pl2.PAPERID, pl2.RVID ) t2 ON t1.PAPERID = t2.PAPERID AND t1.RVID = t2.RVID';
        $sql .= " WHERE NOT EXISTS (SELECT 1 FROM PAPERS p WHERE p.PAPERID = t1.PAPERID AND ( p.FLAG = 'imported'";
        $sql .= $startDate ? " OR p.SUBMISSION_DATE < '$startDate'" : '';
        $sql .= ' ))';

        if ($years !== null) {
            $sql .= sprintf(' AND YEAR(t2.max_date) IN (%s)', implode(',', array_map('intval', (array)$years)));
        }

        return $sql;
    }

    /**
     * Reference: the SQL query of the average delays, before they were computed from the delay of each article.
     *
     * @param int[]|string|int|null $years valid years only
     */
    private static function referenceAverageDelaysSql(string $unit, int $latestStatus, ?string $startDate, ?int $rvId, array|string|int|null $years): string
    {
        return 'SELECT year, rvid, ROUND(AVG(t3.delay), 0) AS delay, COUNT(*) AS count FROM ('
            . self::referenceArticleDelaysSql($unit, $latestStatus, $startDate, $rvId, $years)
            . ') t3 GROUP BY rvid, year';
    }

    /**
     * Reference: the SQL query of the papers given a status the year of their first submission, before its conversion to Doctrine.
     */
    private static function referenceSubmittedSameYearSql(int $status): string
    {
        $sql = 'SELECT PAPERS.RVID AS rvid, YEAR(pl.DATE) AS `year`, COUNT(DISTINCT(PAPERS.PAPERID)) AS total';
        $sql .= ' FROM PAPER_LOG pl JOIN PAPERS ON PAPERS.DOCID = pl.DOCID';
        $sql .= ' JOIN (SELECT PAPERID, MIN(SUBMISSION_DATE) AS SUBMISSION_DATE FROM PAPERS GROUP BY PAPERID) firstSubmission';
        $sql .= ' ON firstSubmission.PAPERID = PAPERS.PAPERID AND YEAR(firstSubmission.SUBMISSION_DATE) = YEAR(pl.DATE)';
        $sql .= " WHERE pl.status IS NOT NULL AND pl.status = $status AND PAPERS.FLAG = 'submitted'";
        $sql .= ' GROUP BY rvid, `year` ORDER BY rvid, `year` DESC';

        return $sql;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(string $sql): array
    {
        return $this->entityManager->getConnection()->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @return list<array{int, int, int, int, string, string}> sorted
     */
    private static function normalizeArticleDelays(?array $rows): array
    {
        $normalized = array_map(static fn(array $row): array => [
            (int)$row['year'],
            (int)$row['rvid'],
            (int)$row['delay'],
            (int)$row['PAPERID'],
            (string)$row['min_date'],
            (string)$row['max_date'],
        ], $rows ?? []);
        sort($normalized);

        return $normalized;
    }

    /**
     * The order of the rows is kept: by journal, then from the latest year
     *
     * @param array<int, array<string, mixed>>|null $rows
     * @return list<array{int, int, int}>
     */
    private static function normalizeCounts(?array $rows): array
    {
        return array_map(static fn(array $row): array => [(int)$row['rvid'], (int)$row['year'], (int)$row['total']], $rows ?? []);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{int, int, float, int}> sorted
     */
    private static function normalizeAverageDelays(array $rows): array
    {
        $normalized = array_map(static fn(array $row): array => [(int)$row['year'], (int)$row['rvid'], (float)$row['delay'], (int)$row['count']], $rows);
        sort($normalized);

        return $normalized;
    }

    private function insertData(): void
    {
        $papers = [
            // docid, paperid, rvid, flag, submission date
            [1, 1, self::RVID, 'submitted', '2022-01-10'],
            [2, 2, self::RVID, 'submitted', '2022-02-01'],
            [3, 2, self::RVID, 'submitted', '2022-05-01'],       // second version of the paper 2
            [4, 3, self::RVID, 'submitted', '2023-03-03'],
            [5, 4, self::RVID, 'imported', '2023-01-01'],        // imported: left out
            [6, 5, self::RVID, 'submitted', '2021-12-20'],       // submitted before the start date
            [7, 6, self::RVID, 'submitted', '2022-04-04'],       // accepted without submission in its history
            [8, 7, self::RVID, 'submitted', '2022-06-06'],       // submitted only
            [9, 8, self::OTHER_RVID, 'submitted', '2022-06-01'],
            [10, 9, self::DISABLED_RVID, 'submitted', '2022-01-01'], // disabled journal: left out without journal
            [11, 10, self::RVID, 'submitted', '2022-07-07'],
            [12, 10, self::RVID, 'imported', '2022-07-07'],      // a version of the paper 10 is imported: left out
            [13, 11, self::OTHER_RVID, 'submitted', '2023-11-11'],
            [14, 12, self::RVID, 'submitted', '2023-04-04'],      // its latest acceptance is the one of a temporary version
            [15, 13, self::RVID, 'submitted', '2021-11-11'],      // first version of the paper 13, the year before its acceptance
            [16, 13, self::RVID, 'submitted', '2022-03-03'],
            [17, 14, self::OTHER_RVID, 'submitted', '2023-02-02'], // refused the year of its submission
        ];

        $logs = [
            // paperid, docid, rvid, status, date
            [1, 1, self::RVID, Paper::STATUS_SUBMITTED, '2022-01-10 09:00:00'],
            [1, 1, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-03-15 18:00:00'],
            [1, 1, self::RVID, Paper::STATUS_PUBLISHED, '2022-04-01 08:00:00'],
            [2, 2, self::RVID, Paper::STATUS_SUBMITTED, '2022-02-01 10:00:00'],
            [2, 3, self::RVID, Paper::STATUS_SUBMITTED, '2022-05-01 10:00:00'], // the first submission is kept
            [2, 3, self::RVID, Paper::STATUS_TMP_VERSION_ACCEPTED, '2023-01-20 10:00:00'],
            [2, 3, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2023-02-25 10:00:00'], // the latest acceptance is kept
            [2, 3, self::RVID, Paper::STATUS_PUBLISHED, '2023-06-01 10:00:00'],
            [3, 4, self::RVID, Paper::STATUS_SUBMITTED, '2023-03-03 10:00:00'],
            [3, 4, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2023-03-21 23:30:00'],
            [3, 4, self::RVID, Paper::STATUS_PUBLISHED, '2024-01-05 10:00:00'],
            [4, 5, self::RVID, Paper::STATUS_SUBMITTED, '2023-01-01 10:00:00'],
            [4, 5, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2023-02-01 10:00:00'],
            [5, 6, self::RVID, Paper::STATUS_SUBMITTED, '2021-12-20 10:00:00'],
            [5, 6, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-02-02 10:00:00'],
            [5, 6, self::RVID, Paper::STATUS_PUBLISHED, '2022-09-09 10:00:00'],
            [6, 7, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-08-08 10:00:00'],
            [7, 8, self::RVID, Paper::STATUS_SUBMITTED, '2022-06-06 10:00:00'],
            [8, 9, self::OTHER_RVID, Paper::STATUS_SUBMITTED, '2022-06-01 10:00:00'],
            [8, 9, self::OTHER_RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2023-07-01 10:00:00'],
            [8, 9, self::OTHER_RVID, Paper::STATUS_PUBLISHED, '2023-09-09 10:00:00'],
            [9, 10, self::DISABLED_RVID, Paper::STATUS_SUBMITTED, '2022-01-01 10:00:00'],
            [9, 10, self::DISABLED_RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-12-12 10:00:00'],
            [10, 11, self::RVID, Paper::STATUS_SUBMITTED, '2022-07-07 10:00:00'],
            [10, 11, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-10-10 10:00:00'],
            [11, 13, self::OTHER_RVID, Paper::STATUS_SUBMITTED, '2023-11-11 10:00:00'],
            [11, 13, self::OTHER_RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2024-02-02 10:00:00'],
            [11, 13, self::OTHER_RVID, Paper::STATUS_PUBLISHED, '2024-05-05 10:00:00'],
            [12, 14, self::RVID, Paper::STATUS_SUBMITTED, '2023-04-04 10:00:00'],
            [12, 14, self::RVID, Paper::STATUS_TMP_VERSION_ACCEPTED, '2024-01-15 10:00:00'],
            [13, 15, self::RVID, Paper::STATUS_SUBMITTED, '2021-11-11 10:00:00'],
            [13, 16, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, '2022-06-06 10:00:00'], // accepted the year of its second version only
            [14, 17, self::OTHER_RVID, Paper::STATUS_SUBMITTED, '2023-02-02 10:00:00'],
            [14, 17, self::OTHER_RVID, Paper::STATUS_REFUSED, '2023-05-05 10:00:00'],
            [14, 17, self::OTHER_RVID, Paper::STATUS_REFUSED, '2023-06-06 10:00:00'], // refused twice: counted once
        ];

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $journalDefaults = $this->requiredColumnDefaults(Review::TABLE);

        foreach ([self::RVID => Review::STATUS_ENABLED, self::OTHER_RVID => Review::STATUS_ENABLED, self::DISABLED_RVID => Review::STATUS_DISABLED] as $rvId => $status) {
            $connection->insert(Review::TABLE, array_merge($journalDefaults, ['RVID' => $rvId, 'CODE' => 'journal' . $rvId, 'STATUS' => $status]));
        }

        $paperDefaults = $this->requiredColumnDefaults(Paper::TABLE);

        foreach ($papers as [$docId, $paperId, $rvId, $flag, $date]) {
            $row = array_merge($paperDefaults, [
                'DOCID' => $docId,
                'PAPERID' => $paperId,
                'RVID' => $rvId,
                'STATUS' => Paper::STATUS_SUBMITTED,
                'FLAG' => $flag,
                'SUBMISSION_DATE' => $date . ' 10:00:00',
                'WHEN' => $date . ' 10:00:00',
            ]);

            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row));
        }

        $logDefaults = $this->requiredColumnDefaults(PaperLog::TABLE);

        foreach ($logs as [$paperId, $docId, $rvId, $status, $date]) {
            // "status" is generated from DETAIL
            $connection->insert(PaperLog::TABLE, array_merge($logDefaults, [
                'PAPERID' => $paperId,
                'DOCID' => $docId,
                'RVID' => $rvId,
                'ACTION' => 'status',
                'DETAIL' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
                'DATE' => $date,
            ]));
        }
    }

    /**
     * A value for each NOT NULL column without default, so that only the columns
     * read by the tested query need to be set.
     *
     * @return array<string, int|string>
     */
    private function requiredColumnDefaults(string $table): array
    {
        $defaults = [];
        foreach ($this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName($table)->getColumns() as $column) {
            if (!$column->getNotnull() || $column->getDefault() !== null || $column->getAutoincrement()) {
                continue;
            }

            $type = $column->getType();
            $name = trim($column->getName(), "`\"");

            $defaults[$name] = match (true) {
                $type instanceof \Doctrine\DBAL\Types\IntegerType,
                $type instanceof \Doctrine\DBAL\Types\SmallIntType,
                $type instanceof \Doctrine\DBAL\Types\BigIntType,
                $type instanceof \Doctrine\DBAL\Types\BooleanType => 0,
                $type instanceof \Doctrine\DBAL\Types\DateTimeType,
                $type instanceof \Doctrine\DBAL\Types\DateTimeImmutableType => '2000-01-01 00:00:00',
                $type instanceof \Doctrine\DBAL\Types\DateType => '2000-01-01',
                $type instanceof \Doctrine\DBAL\Types\JsonType => '[]',
                default => '',
            };
        }

        return $defaults;
    }
}
