<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Repository\PapersRepository;
use App\Service\SubmissionFigures;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Runs PapersRepository::getSubmissions() against a real MySQL database (window functions).
 *
 * A submission is not a paper (PAPERID): a refused paper submitted again is a new row with the same PAPERID. The
 * submissions are the rows of the list of papers of the journal in Episciences (obsolete, deleted and removed versions
 * excluded), each in a single category from its last decision.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS, PAPER_LOG and REVIEW tables: it is skipped if one of them already exists,
 * so that it never touches real data.
 */
class PapersSubmissionsDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;
    private const int DISABLED_RVID = 3;
    private const int REPOSITORY = 1;

    private EntityManagerInterface $entityManager;
    private PapersRepository $repository;

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
     * One submission by row of the list of papers of the journal, each in a single category.
     */
    public function testEachSubmissionIsCountedOnceInItsCategory(): void
    {
        $this->assertSame([
            2 => SubmissionFigures::ACCEPTED,   // revised, then accepted
            3 => SubmissionFigures::REFUSED,    // refused...
            4 => SubmissionFigures::OTHER,      // ...then submitted again (same PAPERID), awaiting a decision
            6 => SubmissionFigures::REFUSED,    // refused...
            7 => SubmissionFigures::ACCEPTED,   // ...then submitted again and published
            9 => SubmissionFigures::REFUSED,    // temporary version accepted, then refused: refused only
            10 => SubmissionFigures::REFUSED,   // refused, its new submission deleted
            13 => SubmissionFigures::IMPORTED,
            15 => SubmissionFigures::ACCEPTED,  // reviewed in the journal, its last version imported
            17 => SubmissionFigures::ACCEPTED,  // published, a refusal logged later on its obsolete version
            18 => SubmissionFigures::ACCEPTED,  // published, without acceptance in the history
            19 => SubmissionFigures::OTHER,     // local versions only
        ], array_column($this->repository->getSubmissions(self::RVID), 'category', 'docid'));
    }

    /**
     * As many submissions as rows in the list of papers of the journal (Episciences_PapersManager::getCount()).
     */
    public function testSubmissionsAreTheRowsOfTheListOfPapers(): void
    {
        $listed = (int)$this->entityManager->getConnection()->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE RVID = ? AND STATUS NOT IN (?, ?, ?)', Paper::TABLE),
            [self::RVID, Paper::STATUS_OBSOLETE, Paper::STATUS_DELETED, Paper::STATUS_REMOVED]
        );

        $this->assertCount($listed, $this->repository->getSubmissions(self::RVID));
    }

    public function testWhatTheCategoryIsComputedFrom(): void
    {
        $submissions = array_column($this->repository->getSubmissions(self::RVID), null, 'docid');

        // the new submission of a refused paper: its own submission date
        $this->assertSame('2023-03-10 10:00:00', $submissions[4]['firstSubmissionDate']);
        $this->assertSame(3, $submissions[4]['paperid']);
        // the first submission: the oldest date of its versions; the last refusal of its versions
        $this->assertSame('2021-01-10 10:00:00', $submissions[6]['firstSubmissionDate']);
        $this->assertSame('2021-05-01 10:00:00', $submissions[6]['refusalDate']);
        $this->assertNull($submissions[6]['acceptanceDate']);
        $this->assertSame('2022-08-01 10:00:00', $submissions[7]['acceptanceDate']);
        $this->assertNull($submissions[7]['refusalDate']);
        $this->assertSame(Paper::STATUS_PUBLISHED, $submissions[7]['status']);
        // a version submitted in the journal: not imported
        $this->assertSame('submitted', $submissions[15]['flag']);
        $this->assertSame('imported', $submissions[13]['flag']);
        $this->assertSame('2018-01-06 10:00:00', $submissions[17]['acceptanceDate']);
        // the publication date of the version it ends with: the year of a submission published without acceptance
        $this->assertNull($submissions[18]['acceptanceDate']);
        $this->assertSame('2015-02-01 10:00:00', $submissions[18]['publicationDate']);
        $this->assertNull($submissions[4]['publicationDate']);
        $this->assertSame([2015 => 1, 2017 => 1, 2018 => 1, 2022 => 2], SubmissionFigures::countByYear($this->repository->getSubmissions(self::RVID), SubmissionFigures::ACCEPTED));
        $this->assertSame(self::REPOSITORY, $submissions[2]['repoid']);
        $this->assertNull($submissions[19]['repoid']);
    }

    public function testStartAfterDateFiltersOnTheFirstSubmissionDate(): void
    {
        $this->assertSame([4, 19], array_column($this->repository->getSubmissions(self::RVID, '2023-01-01'), 'docid'));
    }

    /**
     * Without journal, the active journals only.
     */
    public function testAllTheActiveJournalsWithoutJournal(): void
    {
        $this->assertSame(
            [self::RVID => 12, self::OTHER_RVID => 1],
            SubmissionFigures::countByJournal($this->repository->getSubmissions())
        );
    }

    private function insertData(): void
    {
        $papers = [
            // docid, paperid, rvid, status, flag, submission date[, repository]
            [1, 1, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2022-01-10'],
            [2, 1, self::RVID, Paper::STATUS_STRICTLY_ACCEPTED, 'submitted', '2022-01-10'],
            [3, 3, self::RVID, Paper::STATUS_REFUSED, 'submitted', '2022-02-10'],
            [4, 3, self::RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-03-10'],
            [5, 5, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2021-01-10'],
            [6, 5, self::RVID, Paper::STATUS_REFUSED, 'submitted', '2021-01-10'],
            [7, 5, self::RVID, Paper::STATUS_PUBLISHED, 'submitted', '2022-04-01'],
            [8, 8, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2022-11-10'],
            [9, 8, self::RVID, Paper::STATUS_REFUSED, 'submitted', '2022-11-10'],
            [10, 10, self::RVID, Paper::STATUS_REFUSED, 'submitted', '2021-03-10'],
            [11, 10, self::RVID, Paper::STATUS_DELETED, 'submitted', '2021-09-10'],
            [12, 12, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2020-01-10'],
            [13, 13, self::RVID, Paper::STATUS_PUBLISHED, 'imported', '2022-05-10'],
            [14, 14, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2017-01-10'],
            [15, 14, self::RVID, Paper::STATUS_PUBLISHED, 'imported', '2017-01-10'],
            [16, 16, self::RVID, Paper::STATUS_OBSOLETE, 'submitted', '2017-12-01'],
            [17, 16, self::RVID, Paper::STATUS_PUBLISHED, 'submitted', '2017-12-01'],
            [18, 18, self::RVID, Paper::STATUS_PUBLISHED, 'submitted', '2014-01-10'],
            [19, 19, self::RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-05-01', PapersRepository::LOCAL_REPOSITORY],
            [20, 20, self::OTHER_RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-06-10'],
            [21, 21, self::DISABLED_RVID, Paper::STATUS_SUBMITTED, 'submitted', '2023-06-10'],
        ];

        $logs = [
            // docid, status, date
            [2, Paper::STATUS_STRICTLY_ACCEPTED, '2022-06-01'],
            [3, Paper::STATUS_REFUSED, '2022-09-01'],
            [6, Paper::STATUS_REFUSED, '2021-05-01'],
            [7, Paper::STATUS_STRICTLY_ACCEPTED, '2022-08-01'],
            [7, Paper::STATUS_PUBLISHED, '2022-09-01'],
            [9, Paper::STATUS_TMP_VERSION_ACCEPTED, '2023-01-01'],
            [9, Paper::STATUS_REFUSED, '2023-06-01'],
            [10, Paper::STATUS_REFUSED, '2021-08-01'],
            [14, Paper::STATUS_STRICTLY_ACCEPTED, '2017-06-01'],
            [17, Paper::STATUS_STRICTLY_ACCEPTED, '2018-01-06'],
            [16, Paper::STATUS_REFUSED, '2021-02-12'],
            // published from a copy editing status, without acceptance
            [18, Paper::STATUS_CE_READY_TO_PUBLISH, '2014-06-01'],
            [18, Paper::STATUS_PUBLISHED, '2015-02-01'],
        ];
        // docid => publication date
        $publicationDates = [7 => '2022-09-01', 13 => '2022-05-10', 15 => '2017-07-01', 17 => '2018-02-01', 18 => '2015-02-01'];

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $journalDefaults = $this->requiredColumnDefaults(Review::TABLE);

        foreach ([self::RVID => Review::STATUS_ENABLED, self::OTHER_RVID => Review::STATUS_ENABLED, self::DISABLED_RVID => Review::STATUS_DISABLED] as $rvId => $status) {
            $connection->insert(Review::TABLE, array_merge($journalDefaults, ['RVID' => $rvId, 'CODE' => 'journal' . $rvId, 'STATUS' => $status]));
        }

        $paperDefaults = $this->requiredColumnDefaults(Paper::TABLE);
        $paperIds = [];

        foreach ($papers as $paper) {
            [$docId, $paperId, $rvId, $status, $flag, $date] = $paper;
            $paperIds[$docId] = [$paperId, $rvId];
            $row = array_merge($paperDefaults, [
                'DOCID' => $docId,
                'PAPERID' => $paperId,
                'RVID' => $rvId,
                'STATUS' => $status,
                'FLAG' => $flag,
                'REPOID' => $paper[6] ?? self::REPOSITORY,
                'SUBMISSION_DATE' => $date . ' 10:00:00',
                'WHEN' => $date . ' 10:00:00',
                'PUBLICATION_DATE' => isset($publicationDates[$docId]) ? $publicationDates[$docId] . ' 10:00:00' : null,
            ]);

            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row));
        }

        $logDefaults = $this->requiredColumnDefaults(PaperLog::TABLE);

        foreach ($logs as [$docId, $status, $date]) {
            // "status" is generated from DETAIL
            $connection->insert(PaperLog::TABLE, array_merge($logDefaults, [
                'PAPERID' => $paperIds[$docId][0],
                'DOCID' => $docId,
                'RVID' => $paperIds[$docId][1],
                'ACTION' => 'status',
                'DETAIL' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
                'DATE' => $date . ' 10:00:00',
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
