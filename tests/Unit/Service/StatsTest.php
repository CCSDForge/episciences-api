<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use App\Service\MetadataSources;
use App\Service\Stats;
use App\Traits\ToolsTrait;
use Doctrine\DBAL\Result as DBALResult;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class StatsTest extends TestCase
{
    // ── ToolsTrait::getMedian (pure logic) ───────────────────────────────────

    /** @return object{getMedian(array): int|float} */
    private function traitObject(): object
    {
        return new class {
            use ToolsTrait;
        };
    }

    public function testGetMedian_OddCount(): void
    {
        $result = $this->traitObject()->getMedian([1, 3, 5]);
        $this->assertEquals(3, $result);
    }

    public function testGetMedian_EvenCount(): void
    {
        // median of [2,4] = (2+4)/2 rounded to precision 0
        $result = $this->traitObject()->getMedian([2, 4]);
        $this->assertEquals(3, $result);
    }

    public function testGetMedian_EmptyThrowsLengthException(): void
    {
        $this->expectException(\LengthException::class);
        $this->traitObject()->getMedian([]);
    }

    public function testGetMedian_UnsortedInput(): void
    {
        // should sort before picking median
        $result = $this->traitObject()->getMedian([5, 1, 3]);
        $this->assertEquals(3, $result);
    }

    // ── Stats::getSubmissionsStat ────────────────────────────────────────────

    private function makeStats(
        EntityManagerInterface $em
    ): Stats {
        return new Stats($em, $this->createStub(MetadataSources::class), new NullLogger());
    }

    public function testGetSubmissionsStatReturnsSubmissionOutput(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(42);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $papersRepo = $this->createMock(PapersRepository::class);
        $papersRepo->method('submissionsQuery')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Paper::class)->willReturn($papersRepo);

        $stats = $this->makeStats($em);

        $result = $stats->getSubmissionsStat(['is' => []]);

        $this->assertInstanceOf(SubmissionOutput::class, $result);
        $this->assertSame(42, $result->getValue());
        $this->assertSame('nbSubmissions', $result->getName());
    }

    public function testGetDashboardReturnsEmptyWhenNoSubmissions(): void
    {
        // When nbSubmissions = 0, getDashboard() returns empty DashboardOutput early
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $papersRepo = $this->createMock(PapersRepository::class);
        $papersRepo->method('submissionsQuery')->willReturn($qb);

        // getDashboard() fetches both Paper and PaperLog repos before the early-return check
        $paperLogRepo = $this->createStub(PaperLogRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn(string $class) => match ($class) {
                Paper::class    => $papersRepo,
                PaperLog::class => $paperLogRepo,
                default         => null,
            }
        );

        $stats = $this->makeStats($em);

        $result = $stats->getDashboard(
            ['filters' => []],
            ['is' => []]
        );

        $this->assertNull($result->getValue());
    }

    public function testGetSubmissionsStatHandlesNoResultException(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willThrowException(new NoResultException());

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $papersRepo = $this->createMock(PapersRepository::class);
        $papersRepo->method('submissionsQuery')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Paper::class)->willReturn($papersRepo);

        $stats = $this->makeStats($em);

        $result = $stats->getSubmissionsStat(['is' => []]);

        $this->assertInstanceOf(SubmissionOutput::class, $result);
        $this->assertNull($result->getValue());
    }

    // ── Stats::getUserStats ──────────────────────────────────────────────────

    /**
     * Build a Stats instance with a mocked UserRepository returning $userStats rows
     * and a scalar count of $nbUsers.
     */
    private function makeStatsWithUserRepo(array $userStats, int $nbUsers): Stats
    {
        $scalarQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $scalarQuery->method('getSingleScalarResult')->willReturn($nbUsers);

        $arrayQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $arrayQuery->method('getArrayResult')->willReturn($userStats);

        $qbArray = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbArray->method('getQuery')->willReturn($arrayQuery);

        $qbScalar = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbScalar->method('getQuery')->willReturn($scalarQuery);

        $userRepo = $this->createMock(UserRepository::class);
        // First call (withDetails=true) → array query; second call (withDetails=false) → scalar query
        $userRepo->method('findByReviewQuery')
            ->willReturnOnConsecutiveCalls($qbArray, $qbScalar);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepo);

        return $this->makeStats($em);
    }

    public function testGetUserStatsReturnsUsersStatsOutput(): void
    {
        $stats = $this->makeStatsWithUserRepo([], 0);

        $result = $stats->getUserStats([]);

        $this->assertInstanceOf(UsersStatsOutput::class, $result);
        $this->assertSame('nbUsers', $result->getName());
        $this->assertSame(0, $result->getValue());
    }

    public function testGetUserStatsWithNoDetailsReturnsNullDetails(): void
    {
        $stats = $this->makeStatsWithUserRepo([], 5);

        $result = $stats->getUserStats(['rvid' => 7]);

        $this->assertNull($result->getDetails());
        $this->assertSame(5, $result->getValue());
    }

    public function testGetUserStatsWithDetailsAndRvIdNoRole(): void
    {
        // Simulate DB rows: rvid=7 has ROLE_REVIEWER (3 users) and ROLE_EDITOR (1 user)
        $userStats = [
            ['rvid' => 7, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 3],
            ['rvid' => 7, 'role' => 'ROLE_EDITOR',   'nbUsers' => 1],
            ['rvid' => 9, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 2],
        ];

        $stats = $this->makeStatsWithUserRepo($userStats, 4);

        $result = $stats->getUserStats(['rvid' => 7, 'withDetails' => true]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // Should be keyed by role for rvid=7 only
        $this->assertArrayHasKey('ROLE_REVIEWER', $details);
        $this->assertArrayHasKey('ROLE_EDITOR', $details);
        $this->assertSame(3, $details['ROLE_REVIEWER']['nbUsers']);
        $this->assertSame(1, $details['ROLE_EDITOR']['nbUsers']);
        // rvid=9 data must NOT appear at the top level
        $this->assertArrayNotHasKey(9, $details);
    }

    public function testGetUserStatsWithDetailsRoleNoRvId(): void
    {
        // No rvid, filter by role → details should be grouped by role key
        $userStats = [
            ['rvid' => 5, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 2],
            ['rvid' => 7, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 4],
            ['rvid' => 7, 'role' => 'ROLE_EDITOR',   'nbUsers' => 1],
        ];

        $stats = $this->makeStatsWithUserRepo($userStats, 6);

        $result = $stats->getUserStats(['role' => 'ROLE_REVIEWER', 'withDetails' => true]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // applyFilterBy groups under the filter value key
        $this->assertArrayHasKey('ROLE_REVIEWER', $details);
        // ROLE_EDITOR entries should not appear under ROLE_REVIEWER key
        foreach ($details['ROLE_REVIEWER'] as $item) {
            $this->assertArrayNotHasKey('role', $item, 'role key was unset by applyFilterBy');
        }
    }

    public function testGetUserStatsWithDetailsRvIdAndRole(): void
    {
        // rvid=7, role=ROLE_REVIEWER
        $userStats = [
            ['rvid' => 7, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 5],
            ['rvid' => 7, 'role' => 'ROLE_EDITOR',   'nbUsers' => 2],
        ];

        $stats = $this->makeStatsWithUserRepo($userStats, 5);

        $result = $stats->getUserStats(['rvid' => 7, 'role' => 'ROLE_REVIEWER', 'withDetails' => true]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // Should be the specific role entry: ['nbUsers' => 5]
        $this->assertArrayHasKey('nbUsers', $details);
        $this->assertSame(5, $details['nbUsers']);
    }

    public function testGetUserStatsWithDetailsNoRvIdNoRole(): void
    {
        // All data returned in reformatted structure
        $userStats = [
            ['rvid' => 3, 'role' => 'ROLE_REVIEWER', 'nbUsers' => 8],
            ['rvid' => 5, 'role' => 'ROLE_EDITOR',   'nbUsers' => 2],
        ];

        $stats = $this->makeStatsWithUserRepo($userStats, 10);

        $result = $stats->getUserStats(['withDetails' => true]);

        $details = $result->getDetails();
        $this->assertIsArray($details);
        // reformatUsersData should produce [$rvId => [$role => ['nbUsers' => N]]]
        $this->assertArrayHasKey(3, $details);
        $this->assertArrayHasKey(5, $details);
        $this->assertSame(8, $details[3]['ROLE_REVIEWER']['nbUsers']);
        $this->assertSame(2, $details[5]['ROLE_EDITOR']['nbUsers']);
    }

    public function testGetUserStatsHandlesNoResultException(): void
    {
        $arrayQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $arrayQuery->method('getArrayResult')->willReturn([]);

        $scalarQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $scalarQuery->method('getSingleScalarResult')->willThrowException(new NoResultException());

        $qbArray  = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbArray->method('getQuery')->willReturn($arrayQuery);

        $qbScalar = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbScalar->method('getQuery')->willReturn($scalarQuery);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByReviewQuery')
            ->willReturnOnConsecutiveCalls($qbArray, $qbScalar);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->makeStats($em)->getUserStats([]);

        $this->assertNull($result->getValue());
    }

    // ── Stats::getJournal ─────────────────────────────────────────────────────

    public function testGetJournalDelegatesToReviewRepository(): void
    {
        $journal = $this->createStub(Review::class);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')
            ->with('myjournal')
            ->willReturn($journal);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Review::class)->willReturn($reviewRepo);

        $result = $this->makeStats($em)->getJournal(['code' => 'myjournal']);

        $this->assertSame($journal, $result);
    }

    public function testGetJournalReturnsNullWhenNotFound(): void
    {
        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Review::class)->willReturn($reviewRepo);

        $result = $this->makeStats($em)->getJournal(['code' => 'unknown']);

        $this->assertNull($result);
    }

    // ── Stats::getDelayBetweenSubmissionAndLatestStatus ───────────────────────

    private function makePaperLogRepo(array $delayResult = []): PaperLogRepository
    {
        $repo = $this->createMock(PaperLogRepository::class);
        $repo->method('delayBetweenSubmissionAndLatestStatus')->willReturn($delayResult);
        return $repo;
    }

    private function makeEmWithPaperLogRepo(array $delayResult = []): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(PaperLog::class)
            ->willReturn($this->makePaperLogRepo($delayResult));
        return $em;
    }

    private function makeBaseFilters(): array
    {
        return ['is' => ['rvid' => '5']];
    }

    public function testGetDelayReturnsAcceptanceOutputByDefault(): void
    {
        $em = $this->makeEmWithPaperLogRepo();
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($this->makeBaseFilters());

        $this->assertInstanceOf(SubmissionAcceptanceDelayOutput::class, $result);
    }

    public function testGetDelayReturnsPublicationOutputWhenStatusPublished(): void
    {
        $em = $this->makeEmWithPaperLogRepo();
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus(
            $this->makeBaseFilters(),
            Paper::STATUS_PUBLISHED
        );

        $this->assertInstanceOf(SubmissionPublicationDelayOutput::class, $result);
    }

    public function testGetDelayInvalidUnitDefaultsToWeek(): void
    {
        $em = $this->makeEmWithPaperLogRepo([['delay' => 5]]);

        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus(
            $this->makeBaseFilters(),
            Paper::STATUS_STRICTLY_ACCEPTED,
            'average',
            'invalid_unit'  // invalid → should default to WEEK
        );

        $value = $result->getValue();
        $this->assertSame('WEEK', $value['unit'] ?? null);
    }

    public function testGetDelaySetsAvailableFilters(): void
    {
        $em = $this->makeEmWithPaperLogRepo();
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($this->makeBaseFilters());

        $this->assertNotEmpty($result->getAvailableFilters());
    }

    public function testGetDelaySetsRequestedFiltersFromIs(): void
    {
        $em = $this->makeEmWithPaperLogRepo();
        $filters = ['is' => ['rvid' => '7', 'submissionDate' => '2022']];

        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        // Note: getDelayBetweenSubmissionAndLatestStatus adds 'withDetails'=false to filters['is']
        $requested = $result->getRequestedFilters();
        $this->assertSame('7', $requested['rvid']);
        $this->assertSame('2022', $requested['submissionDate']);
        $this->assertArrayHasKey('withDetails', $requested);
    }

    public function testGetDelayMedianMethodSetsMedianInValue(): void
    {
        // processDelay with median method
        $em = $this->makeEmWithPaperLogRepo([
            ['delay' => 10],
            ['delay' => 20],
            ['delay' => 30],
        ]);

        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus(
            $this->makeBaseFilters(),
            Paper::STATUS_STRICTLY_ACCEPTED,
            'median'
        );

        $value = $result->getValue();
        $this->assertSame('median', $value['method'] ?? null);
        $this->assertNotNull($value['value']); // median of [10, 20, 30] = 20
    }

    public function testGetDelayYearOnlyBranchReturnsAverageForYear(): void
    {
        // year set, rvId NOT set → "all platform by year" branch (line 268)
        $em = $this->makeEmWithPaperLogRepo([
            ['year' => '2022', 'delay' => 10],
            ['year' => '2022', 'delay' => 20],
        ]);

        $filters = ['is' => ['submissionDate' => '2022']]; // no rvid key
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        $value = $result->getValue();
        $this->assertArrayHasKey('value', $value);
        $this->assertArrayHasKey('unit', $value);
    }

    public function testGetDelayYearOnlyBranchReturnsDefaultValueWhenYearNotInResult(): void
    {
        // year set but result has no matching year → $avg === null → default value returned
        $em = $this->makeEmWithPaperLogRepo([
            ['year' => '2020', 'delay' => 10], // different year
        ]);

        $filters = ['is' => ['submissionDate' => '2022']]; // no rvid key
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        $value = $result->getValue();
        $this->assertNull($value['value']);
    }

    public function testGetDelayYearAndRvIdBranchReturnsResource(): void
    {
        // year && rvId branch (line 308)
        $em = $this->makeEmWithPaperLogRepo([
            ['rvid' => 5, 'year' => '2022', 'delay' => 15],
        ]);

        $filters = ['is' => ['rvid' => '5', 'submissionDate' => '2022']];
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        $this->assertInstanceOf(SubmissionAcceptanceDelayOutput::class, $result);
    }

    public function testGetDelayNoYearNoRvIdBranchReturnsGlobalAverage(): void
    {
        // !year && !rvId branch (line 327)
        $em = $this->makeEmWithPaperLogRepo([
            ['delay' => 5],
            ['delay' => 15],
        ]);

        $filters = ['is' => []]; // no rvid, no year
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        $value = $result->getValue();
        $this->assertArrayHasKey('value', $value);
        // Average of [5, 15] = 10
        $this->assertEquals(10, $value['value']);
    }

    public function testGetDelayWithDetailsAndYearOnlyBranchSetsDetails(): void
    {
        $rows = [
            ['year' => '2022', 'delay' => 10],
        ];
        $em = $this->makeEmWithPaperLogRepo($rows);

        $filters = ['is' => ['submissionDate' => '2022', 'withDetails' => true]];
        $result = $this->makeStats($em)->getDelayBetweenSubmissionAndLatestStatus($filters);

        // withDetails was set → details should be non-empty
        $this->assertNotNull($result->getDetails());
    }

    // ── Stats::getNbPapersByStatus ────────────────────────────────────────────

    /**
     * Build a DBAL Statement mock that will return $rows from fetchAllAssociative().
     */
    private function makeDbalStatement(array $rows): Statement
    {
        $dbalResult = $this->createMock(DBALResult::class);
        $dbalResult->method('fetchAllAssociative')->willReturn($rows);

        $stmt = $this->createMock(Statement::class);
        $stmt->method('executeQuery')->willReturn($dbalResult);

        return $stmt;
    }

    private function makeEmWithPaperLogRepoForNbPapers(?Statement $stmt): EntityManagerInterface
    {
        $paperLogRepo = $this->createMock(PaperLogRepository::class);
        $paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn($stmt);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(PaperLog::class)->willReturn($paperLogRepo);

        return $em;
    }

    public function testGetNbPapersByStatusReturnsEmptyWhenStmtIsNull(): void
    {
        $em = $this->makeEmWithPaperLogRepoForNbPapers(null);
        $result = $this->makeStats($em)->getNbPapersByStatus();

        $this->assertSame([], $result);
    }

    public function testGetNbPapersByStatusReturnsRawResultWhenNoRvId(): void
    {
        $rows = [
            ['rvid' => 7, 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 3],
            ['rvid' => 9, 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 5],
        ];
        $em = $this->makeEmWithPaperLogRepoForNbPapers($this->makeDbalStatement($rows));

        $result = $this->makeStats($em)->getNbPapersByStatus(); // no $rvId → returns raw

        $this->assertCount(2, $result);
        $this->assertSame($rows, $result);
    }

    public function testGetNbPapersByStatusWithRvIdFiltersAndReformats(): void
    {
        // DB returns mixed rvids; filter by rvid=7
        $rows = [
            ['rvid' => '7', 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 4],
            ['rvid' => '9', 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 1],
        ];
        $em = $this->makeEmWithPaperLogRepoForNbPapers($this->makeDbalStatement($rows));

        $result = $this->makeStats($em)->getNbPapersByStatus(7);

        // After filter by rvid=7 and reformat: [7 => [2022 => [TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 4]]]
        $this->assertArrayHasKey(7, $result);
        $this->assertArrayHasKey(2022, $result[7]);
        $this->assertSame(4, $result[7][2022][Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR]);
    }

    public function testGetNbPapersByStatusWithRvIdReturnsFilteredEmptyArray(): void
    {
        // No rows for rvid=99 → filtered is [$rvId => []]
        $rows = [
            ['rvid' => '7', 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 4],
        ];
        $em = $this->makeEmWithPaperLogRepoForNbPapers($this->makeDbalStatement($rows));

        $result = $this->makeStats($em)->getNbPapersByStatus(99);

        // applyFilterBy returns [99 => []] when nothing matches
        $this->assertArrayHasKey(99, $result);
        $this->assertSame([], $result[99]);
    }

    public function testGetNbPapersByStatusUsesStatusConstantByDefault(): void
    {
        $paperLogRepo = $this->createMock(PaperLogRepository::class);
        $paperLogRepo->expects($this->once())
            ->method('totalNbPapersByStatusStatement')
            ->with(true, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, Paper::STATUS_STRICTLY_ACCEPTED)
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(PaperLog::class)->willReturn($paperLogRepo);

        $this->makeStats($em)->getNbPapersByStatus();
    }

    // ── Stats::getSubmissionByYearStats ───────────────────────────────────────

    /**
     * Build an EntityManager mock for getSubmissionByYearStats tests.
     *
     * @param array $yearRange        returned by getSubmissionYearRange
     * @param int   $submissionCount  returned by submissionsQuery→getSingleScalarResult
     */
    private function makeEmForSubmissionByYear(
        array $yearRange,
        int $submissionCount = 5,
        bool $throwOnQuery = false
    ): EntityManagerInterface {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        if ($throwOnQuery) {
            $query->method('getSingleScalarResult')->willThrowException(new NoResultException());
        } else {
            $query->method('getSingleScalarResult')->willReturn($submissionCount);
        }

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('getSubmissionYearRange')->willReturn($yearRange);
        $paperRepo->method('getAvailableRepositories')->willReturn([]);
        $paperRepo->method('submissionsQuery')->willReturn($qb);
        $paperRepo->method('getSubmissionsWithoutImported')->willReturn(8);

        $paperLogRepo = $this->createMock(PaperLogRepository::class);
        $paperLogRepo->method('getAccepted')->willReturn(3);
        $paperLogRepo->method('getRefused')->willReturn(1);
        $paperLogRepo->method('getPublished')->willReturn(2);
        $paperLogRepo->method('getAllAcceptedNotYetPublished')->willReturn(1);
        $paperLogRepo->method('getAcceptanceRate')->willReturn(75.0);
        $paperLogRepo->method('totalNbPapersByStatusStatement')->willReturn(null); // getNbPapersByStatus returns []

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Paper::class, $paperRepo],
            [PaperLog::class, $paperLogRepo],
        ]);

        return $em;
    }

    public function testGetSubmissionByYearStatsWithEmptyYearRangeDoesNothing(): void
    {
        $em = $this->makeEmForSubmissionByYear([]);
        $details = [];

        $this->makeStats($em)->getSubmissionByYearStats(['is' => []], null, $details);

        $this->assertArrayNotHasKey(Stats::SUBMISSIONS_BY_YEAR, $details);
    }

    public function testGetSubmissionByYearStatsPopulatesDetailsPerYear(): void
    {
        $em = $this->makeEmForSubmissionByYear([2022], 10);
        $details = [];

        $this->makeStats($em)->getSubmissionByYearStats(['is' => []], 7, $details);

        $this->assertArrayHasKey(Stats::SUBMISSIONS_BY_YEAR, $details);
        $this->assertArrayHasKey(2022, $details[Stats::SUBMISSIONS_BY_YEAR]);

        $year = $details[Stats::SUBMISSIONS_BY_YEAR][2022];
        $this->assertSame(10, $year['submissions']);
        $this->assertSame(10, $year['imported']);  // same mock returns 10 for all submissionsQuery calls
        $this->assertSame(3, $year['accepted']);
        $this->assertSame(1, $year['refused']);
        $this->assertSame(2, $year['published']);
        $this->assertSame(1, $year['acceptedNotYetPublished']);
        $this->assertArrayHasKey('others', $year);
        $this->assertArrayHasKey(Stats::ACCEPTANCE_RATE, $year);
    }

    public function testGetSubmissionByYearStatsPopulatesMultipleYears(): void
    {
        $em = $this->makeEmForSubmissionByYear([2021, 2022], 5);
        $details = [];

        $this->makeStats($em)->getSubmissionByYearStats(['is' => []], 7, $details);

        $this->assertArrayHasKey(2021, $details[Stats::SUBMISSIONS_BY_YEAR]);
        $this->assertArrayHasKey(2022, $details[Stats::SUBMISSIONS_BY_YEAR]);
    }

    public function testGetSubmissionByYearStatsCatchesNoResultExceptionAndSkipsYear(): void
    {
        $em = $this->makeEmForSubmissionByYear([2022], 0, throwOnQuery: true);
        $details = [];

        // Logger must receive an error call (exception is caught and logged)
        $this->makeStats($em)->getSubmissionByYearStats(['is' => []], 7, $details);

        // Year should NOT be populated when query throws
        $this->assertArrayNotHasKey(2022, $details[Stats::SUBMISSIONS_BY_YEAR] ?? []);
    }

    public function testGetSubmissionByYearStatsOthersIsAtLeastZero(): void
    {
        // accepted(3) + refused(1) = 4, submissionsWithoutImported = 8 → others = max(0, 8-4) = 4
        $em = $this->makeEmForSubmissionByYear([2022], 5);
        $details = [];

        $this->makeStats($em)->getSubmissionByYearStats(['is' => []], 7, $details);

        $others = $details[Stats::SUBMISSIONS_BY_YEAR][2022]['others'];
        $this->assertGreaterThanOrEqual(0, $others);
    }
}
