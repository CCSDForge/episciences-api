<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineExtensions\Query\Mysql\Year;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Runs PapersRepository::getSubmissionsAwaitingDecision() ("other-status" of the statistics) and
 * PaperLogRepository::getAllAcceptedNotYetPublished() ("being-to-publish.accepted") against a real MySQL database:
 * the decisions are read from the history, and an abandoned article, only paused, is still counted.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS and PAPER_LOG tables: it is skipped if one of them already exists,
 * so that it never touches real data.
 */
class PapersAwaitingDecisionDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;

    private EntityManagerInterface $entityManager;
    private PapersRepository $repository;
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

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $config);

        if ($connection->createSchemaManager()->tablesExist([Paper::TABLE]) || $connection->createSchemaManager()->tablesExist([PaperLog::TABLE])) {
            $connection->close();
            $this->markTestSkipped(sprintf('The %s or %s table already exists: the test does not touch existing data.', Paper::TABLE, PaperLog::TABLE));
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = [$this->entityManager->getClassMetadata(Paper::class), $this->entityManager->getClassMetadata(PaperLog::class)];

        // Only PAPERS and PAPER_LOG are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tablesCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new PapersRepository($registry, new NullLogger());
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
     * Awaiting a decision: paper 1 (latest version under review), paper 6 (abandoned, i.e. paused) and paper 7 (revision requested);
     * paper 9 is refused in its history, then reopened: it is counted as refused, not twice.
     */
    public function testSubmissionsAwaitingDecisionAreCounted(): void
    {
        $this->assertSame(3, $this->repository->getSubmissionsAwaitingDecision(self::RVID));
    }

    public function testYearFiltersOnTheSubmissionDate(): void
    {
        $this->assertSame(1, $this->repository->getSubmissionsAwaitingDecision(self::RVID, null, [2022]));
        $this->assertSame(2, $this->repository->getSubmissionsAwaitingDecision(self::RVID, null, [2023]));
        $this->assertSame(0, $this->repository->getSubmissionsAwaitingDecision(self::RVID, null, [2021]));
    }

    /**
     * Dashboard detail by year: the same counts as with a year filter (paper 1 in 2022; papers 6 and 7 in 2023).
     */
    public function testSubmissionsAwaitingDecisionAreCountedByYear(): void
    {
        $byYear = $this->repository->getSubmissionsAwaitingDecisionByYear(self::RVID);
        ksort($byYear);

        $this->assertSame([2022 => 1, 2023 => 2], $byYear);

        foreach ($byYear as $year => $total) {
            $this->assertSame($this->repository->getSubmissionsAwaitingDecision(self::RVID, null, [$year]), $total, "Year $year");
        }

        $this->assertSame([2023 => 2], $this->repository->getSubmissionsAwaitingDecisionByYear(self::RVID, '2023-01-01'));
    }

    public function testStartAfterDateFiltersOnTheSubmissionDate(): void
    {
        $this->assertSame(2, $this->repository->getSubmissionsAwaitingDecision(self::RVID, '2023-01-01'));
    }

    public function testAllTheJournalsAreCountedWithoutJournal(): void
    {
        $this->assertSame(4, $this->repository->getSubmissionsAwaitingDecision());
    }

    /**
     * Paper 3 (accepted, being copy edited) and paper 11 (accepted, then abandoned: only paused).
     */
    public function testAcceptedNotYetPublishedIncludesTheAbandonedArticles(): void
    {
        $this->assertSame(2, $this->paperLogRepository->getAllAcceptedNotYetPublished(self::RVID));
    }

    /**
     * The statistics grouped by journal (groupBy=journal) give, for each journal, the counts of a single journal.
     */
    public function testCountsByJournalMatchTheCountsOfEachJournal(): void
    {
        $submissions = $this->repository->countSubmissionsByJournal(['is' => []]);

        foreach ([self::RVID, self::OTHER_RVID] as $rvId) {
            $single = (int)$this->repository->submissionsQuery(['is' => ['rvid' => $rvId]])->getQuery()->getSingleScalarResult();
            $this->assertSame($single, $submissions[$rvId] ?? 0, "Submissions of journal $rvId");
        }

        foreach ([[], [2024], [2023, 2024]] as $years) {
            $accepted = $this->paperLogRepository->getAcceptedByJournal($years);
            $refused = $this->paperLogRepository->getRefusedByJournal($years);

            foreach ([self::RVID, self::OTHER_RVID] as $rvId) {
                $this->assertSame($this->paperLogRepository->getAccepted($rvId, $years), $accepted[$rvId] ?? 0, "Accepted, journal $rvId");
                $this->assertSame($this->paperLogRepository->getRefused($rvId, $years), $refused[$rvId] ?? 0, "Refused, journal $rvId");
            }
        }

        // paper 3 and paper 11 accepted, paper 4 and paper 9 refused (logs of 2024)
        $this->assertSame([self::RVID => 2], $this->paperLogRepository->getAcceptedByJournal());
        $this->assertSame([self::RVID => 2], $this->paperLogRepository->getRefusedByJournal([2024]));
    }

    private function insertData(): void
    {
        $papers = [
            // docid, paperid, rvid, status, flag, submission date
            [1, 1, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2022-03-01'],          // replaced by version 2
            [2, 1, self::RVID, Paper::STATUS_BEING_REVIEWED, 'submitted', '2022-03-01'],    // awaiting a decision
            [3, 2, self::RVID, Paper::STATUS_PUBLISHED, 'submitted', '2022-04-01'],
            [4, 3, self::RVID, Paper::STATUS_CE_READY_TO_PUBLISH, 'submitted', '2022-05-01'], // accepted (history)
            [5, 4, self::RVID, Paper::STATUS_REFUSED, 'submitted', '2023-01-10'],
            [6, 5, self::RVID, Paper::STATUS_DELETED, 'submitted', '2023-02-10'],
            [7, 6, self::RVID, Paper::STATUS_ABANDONED, 'submitted', '2023-03-10'],              // paused: awaiting a decision
            [8, 7, self::RVID, Paper::STATUS_WAITING_FOR_MINOR_REVISION, 'submitted', '2023-04-10'], // awaiting a decision
            [9, 8, self::RVID, Paper::STATUS_SUBMITTED, 'imported', '2023-05-10'],
            [10, 9, self::RVID, Paper::STATUS_WAITING_FOR_MINOR_REVISION, 'submitted', '2021-06-10'], // refused, then reopened
            [11, 10, self::OTHER_RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-06-10'],
            [12, 11, self::RVID, Paper::STATUS_ABANDONED, 'submitted', '2023-07-10'],             // accepted, then paused
        ];

        $logs = [
            // paperid, docid, status
            [3, 4, Paper::STATUS_STRICTLY_ACCEPTED],
            [4, 5, Paper::STATUS_REFUSED],
            [9, 10, Paper::STATUS_REFUSED],
            [9, 10, Paper::STATUS_BEING_REVIEWED],
            [11, 12, Paper::STATUS_STRICTLY_ACCEPTED],
            [11, 12, Paper::STATUS_ABANDONED],
        ];

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $paperDefaults = $this->requiredColumnDefaults(Paper::TABLE);

        foreach ($papers as [$docId, $paperId, $rvId, $status, $flag, $date]) {
            $row = array_merge($paperDefaults, [
                'DOCID' => $docId,
                'PAPERID' => $paperId,
                'RVID' => $rvId,
                'STATUS' => $status,
                'FLAG' => $flag,
                'SUBMISSION_DATE' => $date . ' 10:00:00',
                'WHEN' => $date . ' 10:00:00',
            ]);

            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row));
        }

        $logDefaults = $this->requiredColumnDefaults(PaperLog::TABLE);

        foreach ($logs as [$paperId, $docId, $status]) {
            // "status" is generated from DETAIL
            $connection->insert(PaperLog::TABLE, array_merge($logDefaults, [
                'PAPERID' => $paperId,
                'DOCID' => $docId,
                'RVID' => self::RVID,
                'ACTION' => 'status',
                'DETAIL' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
                'DATE' => '2024-01-01 10:00:00',
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
