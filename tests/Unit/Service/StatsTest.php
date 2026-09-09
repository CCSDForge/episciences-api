<?php

namespace App\Tests\Unit\Service;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\UsersStatsOutput;
use App\Service\MetadataSources;
use App\Service\Stats;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatsTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $em;
    private \PHPUnit\Framework\MockObject\MockObject $metadataSources;
    private \PHPUnit\Framework\MockObject\MockObject $logger;
    private Stats $stats;

    private \PHPUnit\Framework\MockObject\MockObject $paperLogRepo;
    private \PHPUnit\Framework\MockObject\MockObject $papersRepo;
    private \PHPUnit\Framework\MockObject\MockObject $userRepo;
    private \PHPUnit\Framework\MockObject\MockObject $reviewRepo;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->metadataSources = $this->createMock(MetadataSources::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        $this->paperLogRepo = $this->createMock(PaperLogRepository::class);
        $this->papersRepo   = $this->createMock(PapersRepository::class);
        $this->userRepo     = $this->createMock(UserRepository::class);
        $this->reviewRepo   = $this->createMock(ReviewRepository::class);

        $this->em->method('getRepository')->willReturnCallback(fn (string $class): \PHPUnit\Framework\MockObject\MockObject|\App\Repository\PaperLogRepository|\App\Repository\PapersRepository|\App\Repository\UserRepository|\App\Repository\ReviewRepository|null => match ($class) {
            PaperLog::class => $this->paperLogRepo,
            Paper::class    => $this->papersRepo,
            User::class     => $this->userRepo,
            Review::class   => $this->reviewRepo,
            default         => null,
        });

        $this->stats = new Stats($this->em, $this->metadataSources, $this->logger);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns a QueryBuilder mock whose getQuery()->getSingleScalarResult() returns $scalar. */
    private function makeScalarQb(int|float $scalar): MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn($scalar);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    /** Returns a QueryBuilder mock whose getQuery() supports both getArrayResult and getSingleScalarResult. */
    private function makeUserQb(array $arrayResult, int $scalar): MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($arrayResult);
        $query->method('getSingleScalarResult')->willReturn($scalar);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    // -------------------------------------------------------------------------
    // getJournal()
    // -------------------------------------------------------------------------

    public function testGetJournalDelegatesToReviewRepository(): void
    {
        $review = $this->createMock(Review::class);
        $this->reviewRepo
            ->expects($this->once())
            ->method('getJournalByIdentifier')
            ->with('myjournal')
            ->willReturn($review);

        $result = $this->stats->getJournal(['code' => 'myjournal']);

        $this->assertSame($review, $result);
    }

    public function testGetJournalReturnsNullWhenNotFound(): void
    {
        $this->reviewRepo->method('getJournalByIdentifier')->willReturn(null);

        $this->assertNull($this->stats->getJournal(['code' => 'unknown']));
    }

    // -------------------------------------------------------------------------
    // getNbPapersByStatus()
    // -------------------------------------------------------------------------

    public function testGetNbPapersByStatusReturnsEmptyArrayWhenStmtIsNull(): void
    {
        $this->paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn(null);

        $this->assertSame([], $this->stats->getNbPapersByStatus());
    }

    /**
     * Regression: executeQuery() must be called only ONCE when $rvId is null.
     * Before the fix the method called $stmt->executeQuery() twice.
     */
    public function testGetNbPapersByStatusWithNoRvIdCallsExecuteQueryOnce(): void
    {
        $dbResult = [
            ['rvid' => 5, 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 10],
            ['rvid' => 8, 'year' => 2023, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 3],
        ];

        $dbalResult = $this->createMock(Result::class);
        $dbalResult->method('fetchAllAssociative')->willReturn($dbResult);

        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once()) // must NOT be called twice
            ->method('executeQuery')
            ->willReturn($dbalResult);

        $this->paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn($stmt);

        $result = $this->stats->getNbPapersByStatus(null);

        $this->assertSame($dbResult, $result);
    }

    public function testGetNbPapersByStatusFiltersAndReformatsWhenRvIdSet(): void
    {
        $dbResult = [
            ['rvid' => 8, 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 5],
            ['rvid' => 8, 'year' => 2023, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 7],
            ['rvid' => 99, 'year' => 2023, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 1],
        ];

        $dbalResult = $this->createMock(Result::class);
        $dbalResult->method('fetchAllAssociative')->willReturn($dbResult);

        $stmt = $this->createMock(Statement::class);
        $stmt->method('executeQuery')->willReturn($dbalResult);

        $this->paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn($stmt);

        $result = $this->stats->getNbPapersByStatus(8);

        $this->assertArrayHasKey(8, $result);
        $this->assertArrayHasKey(2022, $result[8]);
        $this->assertArrayHasKey(2023, $result[8]);
        $this->assertArrayNotHasKey(99, $result);
        $this->assertSame(['acceptedSubmittedSameYear' => 5], $result[8][2022]);
        $this->assertSame(['acceptedSubmittedSameYear' => 7], $result[8][2023]);
    }

    public function testGetNbPapersByStatusReturnsFilteredEmptyWhenRvIdNotInData(): void
    {
        $dbResult = [
            ['rvid' => 99, 'year' => 2023, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 1],
        ];

        $dbalResult = $this->createMock(Result::class);
        $dbalResult->method('fetchAllAssociative')->willReturn($dbResult);

        $stmt = $this->createMock(Statement::class);
        $stmt->method('executeQuery')->willReturn($dbalResult);

        $this->paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn($stmt);

        $result = $this->stats->getNbPapersByStatus(8);

        $this->assertArrayHasKey(8, $result);
        $this->assertEmpty($result[8]);
    }

    // -------------------------------------------------------------------------
    // getUserStats() — four cases + setDetails-overwrite regression
    // -------------------------------------------------------------------------

    /**
     * Case D: no rvid, no role, withDetails=false → details must be null.
     */
    public function testGetUserStatsWithoutDetailsReturnsNullDetails(): void
    {
        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb([], 5)
        );

        $result = $this->stats->getUserStats([]);

        $this->assertInstanceOf(UsersStatsOutput::class, $result);
        $this->assertSame(5, $result->getValue());
        $this->assertNull($result->getDetails());
    }

    /**
     * Case A: rvid set, no role, withDetails=true — details indexed by role.
     */
    public function testGetUserStatsWithRvIdOnlyReturnsRvIdDetails(): void
    {
        $userStats = [
            ['rvid' => '8', 'role' => 'author', 'nbUsers' => 10],
            ['rvid' => '8', 'role' => 'editor', 'nbUsers' => 2],
            ['rvid' => '99', 'role' => 'author', 'nbUsers' => 50],
        ];

        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb($userStats, 12)
        );

        $result = $this->stats->getUserStats(['rvid' => 8, AppConstants::WITH_DETAILS => true]);

        $this->assertSame(12, $result->getValue());
        $details = $result->getDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('author', $details);
        $this->assertArrayHasKey('editor', $details);
        $this->assertSame(10, $details['author']['nbUsers']);
        $this->assertSame(2, $details['editor']['nbUsers']);
    }

    /**
     * Case B: no rvid, role set, withDetails=true.
     * Regression: before fix the final $details overwrote the role-filtered details
     * with all-platform data, making role filtering ineffective.
     */
    public function testGetUserStatsWithRoleOnlyReturnsRoleFilteredDetails(): void
    {
        $userStats = [
            ['rvid' => '8',  'role' => 'author', 'nbUsers' => 10],
            ['rvid' => '8',  'role' => 'editor', 'nbUsers' => 2],
            ['rvid' => '99', 'role' => 'author', 'nbUsers' => 5],
        ];

        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb($userStats, 15)
        );

        $result = $this->stats->getUserStats(['role' => 'author', AppConstants::WITH_DETAILS => true]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // Must contain only 'author'-filtered rows
        $this->assertArrayHasKey('author', $details);
        // Must NOT contain 'editor' entries since we filtered by role=author
        $this->assertArrayNotHasKey('editor', $details);
    }

    /**
     * Case C: both rvid and role set, withDetails=true.
     * Regression: before fix the details contained all roles for the rvid,
     * not just the requested role.
     */
    public function testGetUserStatsWithRvIdAndRoleReturnsCorrectlyFilteredDetails(): void
    {
        $userStats = [
            ['rvid' => '8',  'role' => 'author', 'nbUsers' => 10],
            ['rvid' => '8',  'role' => 'editor', 'nbUsers' => 2],
            ['rvid' => '99', 'role' => 'author', 'nbUsers' => 5],
        ];

        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb($userStats, 10)
        );

        $result = $this->stats->getUserStats([
            'rvid' => 8,
            'role' => 'author',
            AppConstants::WITH_DETAILS => true,
        ]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // Details for case C is the single-role slice: ['nbUsers' => 10]
        $this->assertArrayHasKey('nbUsers', $details);
        $this->assertSame(10, $details['nbUsers']);
    }

    /**
     * Case D: no rvid, no role, withDetails=true → all-platform data.
     */
    public function testGetUserStatsAllPlatformWithDetails(): void
    {
        $userStats = [
            ['rvid' => '8',  'role' => 'author', 'nbUsers' => 10],
            ['rvid' => '99', 'role' => 'editor', 'nbUsers' => 3],
        ];

        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb($userStats, 13)
        );

        $result = $this->stats->getUserStats([AppConstants::WITH_DETAILS => true]);

        $this->assertSame(13, $result->getValue());
        $details = $result->getDetails();
        $this->assertIsArray($details);
        // All-platform: indexed by rvid
        $this->assertArrayHasKey(8, $details);
        $this->assertArrayHasKey(99, $details);
    }

    public function testGetUserStatsNameIsNbUsers(): void
    {
        $this->userRepo->method('findByReviewQuery')->willReturn(
            $this->makeUserQb([], 0)
        );

        $this->assertSame('nbUsers', $this->stats->getUserStats([])->getName());
    }

    // -------------------------------------------------------------------------
    // getSubmissionsStat()
    // -------------------------------------------------------------------------

    public function testGetSubmissionsStatReturnsCorrectNameAndValue(): void
    {
        $this->papersRepo->method('submissionsQuery')->willReturn($this->makeScalarQb(42));

        $result = $this->stats->getSubmissionsStat(['is' => ['rvid' => null]]);

        $this->assertInstanceOf(SubmissionOutput::class, $result);
        $this->assertSame('nbSubmissions', $result->getName());
        $this->assertSame(42, $result->getValue());
    }

    public function testGetSubmissionsStatWithoutDetailsReturnsEmptyDetailsArray(): void
    {
        $this->papersRepo->method('submissionsQuery')->willReturn($this->makeScalarQb(10));

        $result = $this->stats->getSubmissionsStat(['is' => []]);

        $this->assertSame(10, $result->getValue());
        $this->assertIsArray($result->getDetails());
        $this->assertEmpty($result->getDetails());
    }

    // -------------------------------------------------------------------------
    // getDelayBetweenSubmissionAndLatestStatus()
    // -------------------------------------------------------------------------

    public function testGetDelayReturnsAcceptanceDelayOutputByDefault(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        $this->assertInstanceOf(SubmissionAcceptanceDelayOutput::class, $result);
        $this->assertSame('submissionAcceptanceTime', $result->getName());
    }

    public function testGetDelayReturnsPublicationDelayOutputForPublishedStatus(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => []],
            Paper::STATUS_PUBLISHED
        );

        $this->assertInstanceOf(SubmissionPublicationDelayOutput::class, $result);
        $this->assertSame('submissionPublicationTime', $result->getName());
    }

    public function testGetDelayDefaultUnitIsDay(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        $this->assertSame('DAY', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayAcceptsMonthUnitFromFilter(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_UNIT => 'month']]
        );

        $this->assertSame('MONTH', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayIgnoresInvalidUnitFromFilterAndKeepsParameterUnit(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        // Invalid filter unit → stays at parameter-level default ('day' → 'DAY')
        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_UNIT => 'nanosecond']]
        );

        $this->assertSame('DAY', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayWithInvalidParameterUnitNormalizesToWeek(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        // Passing 'fortnight' as $unit parameter → normalised to 'WEEK'
        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => []],
            Paper::STATUS_STRICTLY_ACCEPTED,
            Stats::DEFAULT_METHOD,
            'fortnight'
        );

        $this->assertSame('WEEK', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayMedianMethodAppendedToName(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => []],
            Paper::STATUS_STRICTLY_ACCEPTED,
            Stats::MEDIAN_METHOD
        );

        $this->assertStringContainsString('Median', $result->getName());
    }

    public function testGetDelayComputesAverageOverAllRows(): void
    {
        $rows = [
            ['delay' => 10],
            ['delay' => 20],
            ['delay' => 30],
        ];

        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        // avg(10, 20, 30) = 20 (rounded to 0 precision)
        $this->assertEquals(20, $result->getValue()['value']);
    }

    public function testGetDelayReturnsNullValueWhenNoRows(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $this->assertNull($this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []])->getValue()['value']);
    }

    public function testGetDelayWithYearFilterAveragesOnlyMatchingYearRows(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 7.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 22.0],
        ];

        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => 2022]]
        );

        // avg(14, 22) = 18 (rounded to 0 precision)
        $this->assertEquals(18, $result->getValue()['value']);
    }

    public function testGetDelayWithRvIdFilterAveragesOnlyMatchingRvIdRows(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 10.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 40.0],
        ];

        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['rvid' => 5]]
        );

        $this->assertEquals(10, $result->getValue()['value']);
    }

    public function testGetDelayMethodCanBeOverriddenFromFilter(): void
    {
        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_METHOD => 'median']]
        );

        $this->assertSame('median', $result->getValue()[Stats::STATS_METHOD]);
    }

    public function testGetDelayWithDetailsAndNoYearNorRvIdSetsDetailsToRawResult(): void
    {
        $rows = [['rvid' => 5, 'year' => 2022, 'delay' => 10.0]];

        $this->paperLogRepo->method('delayBetweenSubmissionAndLatestStatus')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [AppConstants::WITH_DETAILS => true]]
        );

        $this->assertNotNull($result->getDetails());
        $this->assertNotEmpty($result->getDetails());
    }

    public function testGetSubmissionByYearStatsPopulatesDetails(): void
    {
        $filters = ['is' => ['rvid' => 8]];
        $rvId = 8;
        $details = [];

        $this->papersRepo->method('getSubmissionYearRange')->willReturn([2023]);
        $this->papersRepo->method('getAvailableRepositories')->willReturn([1]);
        $this->metadataSources->method('getLabel')->with(1)->willReturn('HAL');

        // Mocking various repository calls inside the loop
        $this->papersRepo->method('submissionsQuery')->willReturn($this->makeScalarQb(10));
        $this->papersRepo->method('getSubmissionsWithoutImported')->willReturn(8);
        
        $this->paperLogRepo->method('getAccepted')->willReturn(5);
        $this->paperLogRepo->method('getRefused')->willReturn(2);
        $this->paperLogRepo->method('getPublished')->willReturn(4);
        $this->paperLogRepo->method('getAllAcceptedNotYetPublished')->willReturn(1);
        $this->paperLogRepo->method('getAcceptanceRate')->willReturn(71.4);
        $this->paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn(null); // Simple case for getNbPapersByStatus

        $this->stats->getSubmissionByYearStats($filters, $rvId, $details);

        $this->assertArrayHasKey(Stats::SUBMISSIONS_BY_YEAR, $details);
        $this->assertArrayHasKey(2023, $details[Stats::SUBMISSIONS_BY_YEAR]);
        $this->assertEquals(10, $details[Stats::SUBMISSIONS_BY_YEAR][2023]['submissions']);
        $this->assertEquals(4, $details[Stats::SUBMISSIONS_BY_YEAR][2023]['published']);
        
        $this->assertArrayHasKey('submissionsByRepo', $details);
        $this->assertArrayHasKey(2023, $details['submissionsByRepo']);
        $this->assertArrayHasKey('HAL', $details['submissionsByRepo'][2023]);
        $this->assertEquals(10, $details['submissionsByRepo'][2023]['HAL']['submissions']);
    }
}
