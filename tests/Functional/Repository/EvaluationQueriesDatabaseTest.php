<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\ReviewerReport;
use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use App\Repository\ReviewerReportRepository;
use App\Repository\UserInvitationRepository;
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

/**
 * Runs the queries of the evaluation statistics (reviews requested and received) against a real MySQL database:
 * the requested reviews are the invitations whatever their answer (except the cancelled ones), and both queries
 * follow the "year" and "startAfterDate" filters.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS, REVIEW, USER_ASSIGNMENT, USER_INVITATION and REVIEWER_REPORT tables:
 * it is skipped if one of them already exists, so that it never touches real data.
 */
class EvaluationQueriesDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int DISABLED_RVID = 2;
    private const string USER_ASSIGNMENT_TABLE = 'USER_ASSIGNMENT';

    private EntityManagerInterface $entityManager;
    private UserInvitationRepository $invitationRepository;
    private ReviewerReportRepository $reportRepository;

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

        foreach ([Paper::TABLE, Review::TABLE, self::USER_ASSIGNMENT_TABLE, UserInvitation::TABLE, ReviewerReport::TABLE] as $table) {
            if ($connection->createSchemaManager()->tablesExist([$table])) {
                $connection->close();
                $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', $table));
            }
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = array_map($this->entityManager->getClassMetadata(...), [Paper::class, Review::class, UserAssignment::class, UserInvitation::class, ReviewerReport::class]);

        // Only these tables are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tablesCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->invitationRepository = new UserInvitationRepository($registry);
        $this->reportRepository = new ReviewerReportRepository($registry);

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
     * Pending, accepted and declined invitations are requested reviews; the cancelled one is not.
     */
    public function testRequestedReviewsAreTheInvitationsWhateverTheirAnswer(): void
    {
        $this->assertSame([1, 2, 3], $this->requestedInvitations(['rvid' => self::RVID]));
    }

    public function testRequestedReviewsCanBeFilteredOnTheStatusOfTheInvitation(): void
    {
        $this->assertSame([4], $this->requestedInvitations(['rvid' => self::RVID, 'invitation-status' => 'cancelled']));
        $this->assertSame([1], $this->requestedInvitations(['rvid' => self::RVID, 'invitation-status' => 'pending']));
    }

    public function testRequestedReviewsFollowTheYearAndTheStartDate(): void
    {
        $this->assertSame([1, 2], $this->requestedInvitations(['rvid' => self::RVID, 'year' => [2023]]));
        $this->assertSame([3], $this->requestedInvitations(['rvid' => self::RVID, 'startAfterDate' => '2024-01-01']));
    }

    /**
     * Without journal, the active journals only: the invitation of the disabled journal is ignored.
     */
    public function testRequestedReviewsOfTheActiveJournalsWithoutJournal(): void
    {
        $this->assertSame([1, 2, 3], $this->requestedInvitations());
    }

    /**
     * One row by completed report (paper and reviewer): the report in progress is not received.
     */
    public function testReceivedReportsFollowTheYearAndTheStartDate(): void
    {
        $this->assertSame(3, $this->countReceivedReports(['rvid' => self::RVID]));
        $this->assertSame(2, $this->countReceivedReports(['rvid' => self::RVID, 'year' => [2023]]));
        $this->assertSame(1, $this->countReceivedReports(['rvid' => self::RVID, 'startAfterDate' => '2024-01-01']));
    }

    /**
     * @return int[] the distinct invitations of the requested reviews
     */
    private function requestedInvitations(array $options = []): array
    {
        $ids = array_values(array_unique(array_map('intval', array_column($this->invitationRepository->getReviewsRequested($options)->getQuery()->getResult(), 'id'))));
        sort($ids);

        return $ids;
    }

    private function countReceivedReports(array $options): int
    {
        return count($this->reportRepository->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))->getQuery()->getArrayResult());
    }

    private function insertData(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quote = static fn(array $row): array => array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row);

        $journalDefaults = $this->requiredColumnDefaults(Review::TABLE);

        foreach ([self::RVID => Review::STATUS_ENABLED, self::DISABLED_RVID => Review::STATUS_DISABLED] as $rvId => $status) {
            $connection->insert(Review::TABLE, array_merge($journalDefaults, ['RVID' => $rvId, 'CODE' => 'journal' . $rvId, 'STATUS' => $status]));
        }

        $paperDefaults = $this->requiredColumnDefaults(Paper::TABLE);

        foreach ([10 => self::RVID, 11 => self::RVID, 12 => self::DISABLED_RVID] as $docId => $rvId) {
            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, $quote(array_merge($paperDefaults, ['DOCID' => $docId, 'PAPERID' => $docId, 'RVID' => $rvId, 'STATUS' => Paper::STATUS_BEING_REVIEWED])));
        }

        $invitations = [
            // id, status, sending date, docid, statuses of the assignments
            [1, 'pending', '2023-03-01', 10, ['pending']],
            [2, 'accepted', '2023-04-01', 10, ['pending', 'active']], // an answered invitation has two assignments
            [3, 'declined', '2024-02-01', 11, ['pending', 'declined']],
            [4, 'cancelled', '2024-03-01', 11, ['cancelled']],
            [5, 'accepted', '2024-03-01', 12, ['pending', 'active']], // disabled journal
        ];

        $invitationDefaults = $this->requiredColumnDefaults(UserInvitation::TABLE);
        $assignmentDefaults = $this->requiredColumnDefaults(self::USER_ASSIGNMENT_TABLE);

        foreach ($invitations as [$id, $status, $date, $docId, $assignments]) {
            $connection->insert(UserInvitation::TABLE, array_merge($invitationDefaults, ['ID' => $id, 'STATUS' => $status, 'SENDING_DATE' => $date . ' 10:00:00', 'EXPIRATION_DATE' => $date . ' 10:00:00']));

            foreach ($assignments as $assignmentStatus) {
                $connection->insert(self::USER_ASSIGNMENT_TABLE, $quote(array_merge($assignmentDefaults, [
                    'INVITATION_ID' => $id,
                    'RVID' => $docId === 12 ? self::DISABLED_RVID : self::RVID,
                    'ITEMID' => $docId,
                    'ITEM' => UserAssignment::ITEM_PAPER,
                    'UID' => 100 + $id,
                    'ROLEID' => UserAssignment::ROLE_REVIEWER,
                    'STATUS' => $assignmentStatus,
                    'WHEN' => $date . ' 10:00:00',
                ])));
            }
        }

        $reports = [
            // uid, docid, status, creation date
            [101, 10, ReviewerReport::STATUS_COMPLETED, '2023-05-01'],
            [102, 10, ReviewerReport::STATUS_COMPLETED, '2023-06-01'],
            [103, 11, ReviewerReport::STATUS_COMPLETED, '2024-02-15'],
            [104, 11, ReviewerReport::STATUS_WIP, '2024-03-15'],
            [105, 12, ReviewerReport::STATUS_COMPLETED, '2024-03-15'], // disabled journal
        ];

        $reportDefaults = $this->requiredColumnDefaults(ReviewerReport::TABLE);

        foreach ($reports as [$uid, $docId, $status, $date]) {
            $connection->insert(ReviewerReport::TABLE, array_merge($reportDefaults, ['UID' => $uid, 'DOCID' => $docId, 'STATUS' => $status, 'CREATION_DATE' => $date . ' 10:00:00']));
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
