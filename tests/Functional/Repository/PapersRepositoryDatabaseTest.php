<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Repository\PapersRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineExtensions\Query\Mysql\Greatest;
use DoctrineExtensions\Query\Mysql\Year;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Runs the grouped submission counts of PapersRepository (dashboard statistics) against a
 * real MySQL database: they must give the same numbers as the per-year submissionsQuery().
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS table: it is skipped if the table already exists,
 * so that it never touches real data.
 */
class PapersRepositoryDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;
    /** Papers with versions submitted in different years, a temporary version and a change of repository */
    private const int VERSIONS_RVID = 3;

    private EntityManagerInterface $entityManager;
    private PapersRepository $repository;

    /** @var ClassMetadata<Paper> */
    private ClassMetadata $metadata;

    private bool $tableCreated = false;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL') ?: ($_SERVER['TEST_DATABASE_URL'] ?? null);

        if (!$url) {
            $this->markTestSkipped('TEST_DATABASE_URL is not set.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->addCustomStringFunction('YEAR', Year::class);
        $config->addCustomNumericFunction('GREATEST', Greatest::class);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $config);

        if ($connection->createSchemaManager()->tablesExist([Paper::TABLE])) {
            $connection->close();
            $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', Paper::TABLE));
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = $this->entityManager->getClassMetadata(Paper::class);

        // Only PAPERS is created: its foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema([$this->metadata]);
        $this->tableCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new PapersRepository($registry, new NullLogger());

        $this->insertPapers();
    }

    protected function tearDown(): void
    {
        if ($this->tableCreated) {
            new SchemaTool($this->entityManager)->dropSchema([$this->metadata]);
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    /**
     * Several years (year[]=2022&year[]=2023 on the dashboard), by first submission and by publication date.
     */
    public function testSeveralYearsAreCounted(): void
    {
        $filters = $this->filters(['submissionDate' => [2022, 2023]]);

        $this->assertSame(4, (int)$this->repository->submissionsQuery($filters)->getQuery()->getSingleScalarResult());
        // no publication date in the fixtures: the query runs and counts nothing
        $this->assertSame(0, (int)$this->repository->submissionsQuery($filters, false, 'publicationDate')->getQuery()->getSingleScalarResult());
    }

    /**
     * A journal without any submission (e.g. a new one) has no year, without the deprecations of range(null, null)
     */
    public function testAJournalWithoutSubmissionHasNoYear(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $this->assertSame([], $this->repository->getYearRange(99));
            $this->assertNotSame([], $this->repository->getYearRange(self::RVID));
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Two versions of the same date: the latest is the one of the latest docid (as the paper lists), not an arbitrary one
     */
    public function testTheLatestVersionOfTheSameDateIsTheLatestDocid(): void
    {
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $this->entityManager->getConnection()->update(Paper::TABLE, [$platform->quoteSingleIdentifier('WHEN') => '2024-05-01 10:00:00'], ['DOCID' => 14]);

        $firstVersion = $this->entityManager->find(Paper::class, 13);
        $secondVersion = $this->entityManager->find(Paper::class, 14);

        $this->assertSame(14, $this->repository->findLatestVersion($firstVersion)?->getDocid());
        $this->assertNull($this->repository->findLatestVersion($secondVersion));
    }

    /**
     * A paper is counted once, in the year of its first submission (paper 10: v1 in 2023, v2 in 2024).
     */
    public function testPaperIsCountedInTheYearOfItsFirstSubmission(): void
    {
        $filters = ['is' => ['rvid' => self::VERSIONS_RVID]];

        $this->assertSame([10], $this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::VERSIONS_RVID, 'year' => [2023]]]));
        $this->assertSame([11], $this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::VERSIONS_RVID, 'year' => [2024]]]));
        $this->assertSame(1, (int)$this->repository->submissionsQuery(['is' => ['rvid' => self::VERSIONS_RVID, 'year' => [2024]]])->getQuery()->getSingleScalarResult());
    }

    /**
     * The start date is compared to the first submission: paper 10, first submitted in 2023, is not counted after 2024-01-01.
     */
    public function testStartAfterDateComparesTheFirstSubmission(): void
    {
        $this->assertSame([11], $this->repository->getSubmissionPaperIds(['is' => ['rvid' => self::VERSIONS_RVID, 'startAfterDate' => '2024-01-01']]));
    }

    /**
     * The temporary versions (local repository) are not counted by repository; paper 11, moved from repository 2
     * to repository 1, is counted in the repository of its first version.
     */
    public function testSubmissionsByRepositoryIgnoreTheTemporaryVersions(): void
    {
        $filters = ['is' => ['rvid' => self::VERSIONS_RVID]];

        $this->assertNotContains(PapersRepository::LOCAL_REPOSITORY, $this->repository->getAvailableRepositories($filters));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{is: array<string, mixed>}
     */
    private function filters(array $filters = []): array
    {
        return ['is' => ['rvid' => self::RVID] + $filters];
    }

    private function insertPapers(): void
    {
        $papers = [
            // docid, paperid, rvid, repoid, status, flag, submission date
            [1, 1, self::RVID, 1, Paper::STATUS_SUBMITTED, 'submitted', '2022-03-01'],
            [2, 1, self::RVID, 1, Paper::STATUS_PUBLISHED, 'submitted', '2022-06-01'], // new version of paper 1
            [3, 2, self::RVID, 2, Paper::STATUS_SUBMITTED, 'submitted', '2022-07-01'],
            [4, 3, self::RVID, 1, Paper::STATUS_PUBLISHED, 'imported', '2023-01-10'],
            [5, 4, self::RVID, 2, Paper::STATUS_SUBMITTED, 'submitted', '2023-02-10'],
            [6, 5, self::RVID, 1, Paper::STATUS_DELETED, 'submitted', '2023-03-10'],
            [7, 6, self::OTHER_RVID, 1, Paper::STATUS_SUBMITTED, 'submitted', '2023-04-10'],
            // paper 10: v1 in 2023, v2 in 2024 (other submission date), then a temporary version (local repository)
            [10, 10, self::VERSIONS_RVID, 1, Paper::STATUS_OBSOLETE, 'submitted', '2023-11-10'],
            [11, 10, self::VERSIONS_RVID, 1, Paper::STATUS_OBSOLETE, 'submitted', '2024-03-10'],
            [12, 10, self::VERSIONS_RVID, PapersRepository::LOCAL_REPOSITORY, Paper::STATUS_TMP_VERSION, 'submitted', '2024-04-01'],
            // paper 11: first in repository 2, then in repository 1
            [13, 11, self::VERSIONS_RVID, 2, Paper::STATUS_OBSOLETE, 'submitted', '2024-05-01'],
            [14, 11, self::VERSIONS_RVID, 1, Paper::STATUS_SUBMITTED, 'submitted', '2024-06-01'],
        ];

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $defaults = $this->requiredColumnDefaults();

        foreach ($papers as [$docId, $paperId, $rvId, $repoId, $status, $flag, $date]) {
            $row = array_merge($defaults, [
                'DOCID' => $docId,
                'PAPERID' => $paperId,
                'RVID' => $rvId,
                'REPOID' => $repoId,
                'STATUS' => $status,
                'FLAG' => $flag,
                'SUBMISSION_DATE' => $date . ' 10:00:00',
                'WHEN' => $date . ' 10:00:00',
            ]);

            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row));
        }
    }

    /**
     * A value for each NOT NULL column without default, so that only the columns
     * read by the tested queries need to be set.
     *
     * @return array<string, int|string>
     */
    private function requiredColumnDefaults(): array
    {
        $defaults = [];
        foreach ($this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName(Paper::TABLE)->getColumns() as $column) {
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
