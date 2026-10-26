<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Service\MetadataSources;
use App\Service\Stats;
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
 * Without journal, only the active journals are taken into account.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS, PAPER_LOG and REVIEW tables: it is skipped if one of them already exists,
 * so that it never touches real data.
 */
class PapersAwaitingDecisionDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;
    private const int DISABLED_RVID = 3;

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

    /**
     * Without journal, the active journals only: the paper of the disabled journal is ignored.
     */
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
        $this->assertArrayNotHasKey(self::DISABLED_RVID, $submissions, 'Disabled journal');

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

    /**
     * withPapers: each list of papers has as many identifiers as its figure (same queries).
     */
    public function testPaperListsExplainTheFigures(): void
    {
        $this->assertSame(
            (int)$this->repository->submissionsQuery(['is' => ['rvid' => self::RVID]])->getQuery()->getSingleScalarResult(),
            count($this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::RVID]]))
        );
        // paper 5 deleted, paper 8 imported
        $this->assertSame([1, 2, 3, 4, 6, 7, 9, 11], $this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::RVID]], 'submitted'));
        $this->assertSame([8], $this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::RVID]], 'imported'));

        foreach ([[], [2024], [2023, 2024]] as $years) {
            $this->assertSame($this->repository->getSubmissionsAwaitingDecision(self::RVID, null, $years), count($this->repository->getSubmissionsAwaitingDecisionPaperIds(self::RVID, null, $years)));
            $this->assertSame($this->paperLogRepository->getAccepted(self::RVID, $years), count($this->paperLogRepository->getPaperIds('accepted', self::RVID, $years)));
            $this->assertSame($this->paperLogRepository->getRefused(self::RVID, $years), count($this->paperLogRepository->getPaperIds('refused', self::RVID, $years)));
            $this->assertSame($this->paperLogRepository->getPublished(self::RVID, $years), count($this->paperLogRepository->getPaperIds('published', self::RVID, $years)));
            $this->assertSame($this->paperLogRepository->getAllAcceptedNotYetPublished(self::RVID, $years), count($this->paperLogRepository->getPaperIds('accepted-not-yet-published', self::RVID, $years)));
        }

        $this->assertSame([1, 6, 7], $this->repository->getSubmissionsAwaitingDecisionPaperIds(self::RVID));
        $this->assertSame([3, 11], $this->paperLogRepository->getPaperIds('accepted', self::RVID));
        $this->assertSame([4, 9], $this->paperLogRepository->getPaperIds('refused', self::RVID));
    }

    /**
     * Paper 3 is accepted in 2023 and in 2024: it is counted in each year, but once in the total of the two years.
     */
    public function testPaperDecidedInSeveralYearsIsCountedOnceInTheTotal(): void
    {
        $this->assertSame([2023 => 1, 2024 => 2], $this->paperLogRepository->getAcceptedByYear(self::RVID, [2023, 2024]));
        $this->assertSame(2, $this->paperLogRepository->getAccepted(self::RVID, [2023, 2024]));
        $this->assertSame([self::RVID => 2], $this->paperLogRepository->getAcceptedByJournal([2023, 2024]));
    }

    /**
     * Average delays of the dashboard (raw SQL): papers 3 and 11 are last accepted in 2024; an invalid year gives no result.
     */
    public function testDelaysAreFilteredOnValidYearsOnly(): void
    {
        $delays = fn(mixed $years, ?int $rvId = self::RVID): ?array => $this->paperLogRepository->delayBetweenSubmissionAndLatestStatus(PaperLogRepository::DEFAULT_UNIT, Paper::STATUS_STRICTLY_ACCEPTED, null, $years, Stats::DEFAULT_METHOD, $rvId);

        $this->assertSame([2024], array_map('intval', array_column($delays([2024]), 'year')));
        $this->assertSame([2024], array_map('intval', array_column($delays('2024', null), 'year')), 'Without journal');
        $this->assertSame([], $delays([2023]));
        $this->assertSame([], $delays(['2024', 'abc']));
        $this->assertSame([], $delays('24'));
    }

    /**
     * The dashboard computes the average delays from the delay of each article (the median query):
     * the same figures as the average query.
     */
    public function testAverageDelaysFromTheArticleDelaysMatchTheAverageQuery(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->paperLogRepository);
        $stats = new Stats($entityManager, $this->createStub(MetadataSources::class), new NullLogger());

        foreach ([[], ['submissionDate' => [2024]]] as $yearFilter) {
            $filters = ['is' => ['rvid' => self::RVID, AppConstants::WITH_DETAILS => true] + $yearFilter];
            $articleDelays = $this->paperLogRepository->delayBetweenSubmissionAndLatestStatus('DAY', Paper::STATUS_STRICTLY_ACCEPTED, null, $yearFilter['submissionDate'] ?? null, Stats::MEDIAN_METHOD, self::RVID);

            $this->assertNotEmpty($articleDelays);

            $fromQuery = $stats->getDelayBetweenSubmissionAndLatestStatus($filters);
            $fromArticles = $stats->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_STRICTLY_ACCEPTED, Stats::DEFAULT_METHOD, 'day', $articleDelays);

            $this->assertEquals($fromQuery->getValue(), $fromArticles->getValue());
            $this->assertEquals($fromQuery->getDetails(), $fromArticles->getDetails());
        }
    }

    /**
     * withPapers lists one DOCID by paper: the one of its latest version (paper 1 has the versions 1 and 2).
     */
    public function testLatestVersionOfEachPaper(): void
    {
        $docIds = $this->repository->getLatestDocIds([1, 2, 1, 999]);
        ksort($docIds);

        $this->assertSame([1 => 2, 2 => 3], $docIds);
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
            [13, 12, self::DISABLED_RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-08-10'],    // disabled journal: ignored without journal
            [14, 13, self::DISABLED_RVID, Paper::STATUS_CE_READY_TO_PUBLISH, 'submitted', '2023-09-10'], // idem (accepted)
        ];

        $logs = [
            // paperid, docid, status[, date]
            [3, 4, Paper::STATUS_SUBMITTED, '2022-05-01'], // start of the delays
            [3, 4, Paper::STATUS_STRICTLY_ACCEPTED, '2023-06-01'], // accepted in 2023, then again in 2024
            [3, 4, Paper::STATUS_STRICTLY_ACCEPTED],
            [4, 5, Paper::STATUS_REFUSED],
            [9, 10, Paper::STATUS_REFUSED],
            [9, 10, Paper::STATUS_BEING_REVIEWED],
            [11, 12, Paper::STATUS_SUBMITTED, '2023-07-10'],
            [11, 12, Paper::STATUS_STRICTLY_ACCEPTED],
            [11, 12, Paper::STATUS_ABANDONED],
            [13, 14, Paper::STATUS_STRICTLY_ACCEPTED],
        ];

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $journalDefaults = $this->requiredColumnDefaults(Review::TABLE);

        foreach ([self::RVID => Review::STATUS_ENABLED, self::OTHER_RVID => Review::STATUS_ENABLED, self::DISABLED_RVID => Review::STATUS_DISABLED] as $rvId => $status) {
            $connection->insert(Review::TABLE, array_merge($journalDefaults, ['RVID' => $rvId, 'CODE' => 'journal' . $rvId, 'STATUS' => $status]));
        }

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

        foreach ($logs as $log) {
            [$paperId, $docId, $status] = $log;
            // "status" is generated from DETAIL
            $connection->insert(PaperLog::TABLE, array_merge($logDefaults, [
                'PAPERID' => $paperId,
                'DOCID' => $docId,
                'RVID' => self::RVID,
                'ACTION' => 'status',
                'DETAIL' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
                'DATE' => ($log[3] ?? '2024-01-01') . ' 10:00:00',
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
