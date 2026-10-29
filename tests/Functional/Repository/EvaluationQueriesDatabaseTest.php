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
 * the requested reviews are the invitations whatever their answer (except the cancelled ones), the received reports
 * are dated by their validation, and both queries follow the "year" and "startAfterDate" filters.
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
     * Pending, accepted and declined invitations are requested reviews, once each; the cancelled ones are not (even
     * cancelled after an answer, or by a cancelled assignment only), nor the invitation without paper.
     */
    public function testRequestedReviewsAreTheInvitationsWhateverTheirAnswer(): void
    {
        $this->assertSame([1, 2, 3], $this->requestedInvitations(['rvid' => self::RVID]));
    }

    public function testRequestedReviewsCanBeFilteredOnTheStatusOfTheInvitation(): void
    {
        // invitation 8 has no cancelled row, only a cancelled assignment
        $this->assertSame([4, 7, 8], $this->requestedInvitations(['rvid' => self::RVID, 'invitation-status' => 'cancelled']));
        $this->assertSame(['cancelled'], array_values(array_unique(array_column($this->invitationRepository->getReviewsRequested(['rvid' => self::RVID, 'invitation-status' => 'cancelled'])->getQuery()->getResult(), 'status'))));
        // the first row of an answered invitation stays pending: only the invitation without answer is pending
        $this->assertSame([1], $this->requestedInvitations(['rvid' => self::RVID, 'invitation-status' => 'pending']));
        $this->assertSame([2], $this->requestedInvitations(['rvid' => self::RVID, 'invitation-status' => 'accepted']));
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
     * One row by completed report (paper version and reviewer): the report in progress is not received.
     * A report is dated by its validation: the report started in 2023 and validated in 2024 is a report of 2024.
     */
    public function testReceivedReportsFollowTheYearAndTheStartDateOfTheirValidation(): void
    {
        $this->assertSame(4, $this->countReceivedReports(['rvid' => self::RVID]));
        $this->assertSame(2, $this->countReceivedReports(['rvid' => self::RVID, 'year' => [2023]]));
        $this->assertSame(2, $this->countReceivedReports(['rvid' => self::RVID, 'year' => [2024]]));
        $this->assertSame(2, $this->countReceivedReports(['rvid' => self::RVID, 'startAfterDate' => '2024-01-01']));
    }

    /**
     * Each report gives its paper: the reports of the two versions of paper 10 are the reports of the same paper.
     */
    public function testReceivedReportsGiveTheirPaper(): void
    {
        $rows = $this->reportRepository->getReceivedReports(['rvid' => self::RVID, 'report-status' => ReviewerReport::STATUS_COMPLETED])->getQuery()->getArrayResult();
        $paperIds = array_map('intval', array_column($rows, 'paperid'));
        sort($paperIds);

        $this->assertSame([10, 10, 10, 11], $paperIds);
    }

    /**
     * The rows give what the lists of the reviews need: the paper of the invitation (conflicts of interest), its sending
     * date and its expiration (of the invitation, not of its answer), its current status; the date of validation of the report.
     */
    public function testRowsGiveThePaperAndTheDateOfTheReview(): void
    {
        $invitations = [];

        foreach ($this->invitationRepository->getReviewsRequested(['rvid' => self::RVID])->getQuery()->getResult() as $row) {
            $invitations[(int)$row['id']] = [(int)$row['paperid'], $row['date']->format('Y-m-d'), $row['expirationDate']->format('Y-m-d'), $row['status']];
        }

        ksort($invitations);
        $this->assertSame([
            1 => [10, '2023-03-01', '2023-03-01', 'pending'],
            2 => [10, '2023-04-01', '2023-04-01', 'accepted'],
            3 => [11, '2024-02-01', '2024-02-01', 'declined'],
        ], $invitations);

        $reports = [];

        foreach ($this->reportRepository->getReceivedReports(['rvid' => self::RVID, 'report-status' => ReviewerReport::STATUS_COMPLETED])->getQuery()->getArrayResult() as $row) {
            $reports[] = [(int)$row['docid'], substr((string)$row['date'], 0, 10)];
        }

        sort($reports);
        $this->assertSame([[10, '2023-05-01'], [10, '2023-06-01'], [11, '2024-02-15'], [13, '2024-01-10']], $reports);
    }

    /**
     * @return int[] the invitations of the rows of the requested reviews, not deduplicated: one row by invitation
     */
    private function requestedInvitations(array $options = []): array
    {
        $ids = array_map('intval', array_column($this->invitationRepository->getReviewsRequested($options)->getQuery()->getResult(), 'id'));
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

        // docid => paperid, rvid: docid 13 is the second version of paper 10
        foreach ([10 => [10, self::RVID], 11 => [11, self::RVID], 12 => [12, self::DISABLED_RVID], 13 => [10, self::RVID]] as $docId => [$paperId, $rvId]) {
            // WHEN is a reserved word
            $connection->insert(Paper::TABLE, $quote(array_merge($paperDefaults, ['DOCID' => $docId, 'PAPERID' => $paperId, 'RVID' => $rvId, 'STATUS' => Paper::STATUS_BEING_REVIEWED])));
        }

        $invitations = [
            // id, sending date, docid, answers (status, date): as on Episciences, the invitation is the row created with the
            // pending assignment (USER_INVITATION.AID); each answer or cancellation inserts another row with the same AID,
            // referred to by an assignment of its own: the latest row gives the status of the invitation
            [1, '2023-03-01', 10, []],
            [2, '2023-04-01', 10, [['accepted', '2024-01-05']]], // sent in 2023, answered in 2024: an invitation of 2023
            [3, '2024-02-01', 11, [['declined', '2024-02-10']]],
            [4, '2024-03-01', 11, [['cancelled', '2024-03-05']]],
            [5, '2024-03-01', 12, [['accepted', '2024-03-02']]], // disabled journal
            [6, '2024-04-01', 0, []], // no paper (ITEMID = 0): not a review requested
            [7, '2023-05-01', 10, [['accepted', '2023-05-02'], ['cancelled', '2023-06-01']]], // accepted, then cancelled
            // without answer, cancelled when the paper was accepted: a cancelled assignment on the first row, no new row
            [8, '2023-06-01', 10, [], '2023-07-01'],
        ];

        // status of the invitation => status of the assignment
        $assignmentStatuses = ['pending' => 'pending', 'accepted' => 'active', 'declined' => 'declined', 'cancelled' => 'cancelled'];

        $invitationDefaults = $this->requiredColumnDefaults(UserInvitation::TABLE);
        $assignmentDefaults = $this->requiredColumnDefaults(self::USER_ASSIGNMENT_TABLE);

        foreach ($invitations as $invitation) {
            [$id, $date, $docId, $answers] = $invitation;
            $cancellationDate = $invitation[4] ?? null;
            $aid = null;

            // the invitation (pending), then its answers: rows 21, 22... for invitation 2
            foreach ([['pending', $date], ...$answers] as $step => [$status, $stepDate]) {
                $invitationId = $step === 0 ? $id : $id * 10 + $step;

                $connection->insert(self::USER_ASSIGNMENT_TABLE, $quote(array_merge($assignmentDefaults, [
                    'INVITATION_ID' => $invitationId,
                    'RVID' => $docId === 12 ? self::DISABLED_RVID : self::RVID,
                    'ITEMID' => $docId,
                    'ITEM' => UserAssignment::ITEM_PAPER,
                    'UID' => 100 + $id,
                    'ROLEID' => UserAssignment::ROLE_REVIEWER,
                    'STATUS' => $assignmentStatuses[$status],
                    'WHEN' => $stepDate . ' 10:00:00',
                ])));
                $aid ??= (int)$connection->lastInsertId();

                $connection->insert(UserInvitation::TABLE, array_merge($invitationDefaults, [
                    'ID' => $invitationId,
                    'AID' => $aid,
                    'STATUS' => $status,
                    'SENDING_DATE' => $stepDate . ' 10:00:00',
                    'EXPIRATION_DATE' => $stepDate . ' 10:00:00',
                ]));
            }

            if ($cancellationDate !== null) {
                $connection->insert(self::USER_ASSIGNMENT_TABLE, $quote(array_merge($assignmentDefaults, [
                    'INVITATION_ID' => $id,
                    'RVID' => self::RVID,
                    'ITEMID' => $docId,
                    'ITEM' => UserAssignment::ITEM_PAPER,
                    'UID' => 100 + $id,
                    'ROLEID' => UserAssignment::ROLE_REVIEWER,
                    'STATUS' => UserAssignment::STATUS_CANCELLED,
                    'WHEN' => $cancellationDate . ' 10:00:00',
                ])));
            }
        }

        $reports = [
            // uid, docid, status, creation date[, update date: the validation of a completed report]
            [101, 10, ReviewerReport::STATUS_COMPLETED, '2023-05-01'],
            [102, 10, ReviewerReport::STATUS_COMPLETED, '2023-05-20', '2023-06-01'],
            [103, 11, ReviewerReport::STATUS_COMPLETED, '2024-02-15'],
            [104, 11, ReviewerReport::STATUS_WIP, '2024-03-15'],
            [105, 12, ReviewerReport::STATUS_COMPLETED, '2024-03-15'], // disabled journal
            [101, 13, ReviewerReport::STATUS_COMPLETED, '2023-12-20', '2024-01-10'], // started in 2023, validated in 2024
        ];

        $reportDefaults = $this->requiredColumnDefaults(ReviewerReport::TABLE);

        foreach ($reports as $report) {
            [$uid, $docId, $status, $date] = $report;
            $connection->insert(ReviewerReport::TABLE, array_merge($reportDefaults, [
                'UID' => $uid,
                'DOCID' => $docId,
                'STATUS' => $status,
                'CREATION_DATE' => $date . ' 10:00:00',
                'UPDATE_DATE' => isset($report[4]) ? $report[4] . ' 10:00:00' : null,
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
