<?php

namespace App\Tests\Unit\Service;

use App\AppConstants;
use App\DataProvider\ReviewStatsDataProvider;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\User;
use App\EventSubscriber\StatResourceSubscriber;
use App\Exception\ResourceNotFoundException;
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
use App\Service\SubmissionFigures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    /**
     * A submission as returned by PapersRepository::getSubmissions()
     *
     * @return array{rvid: int, paperid: int, docid: int, status: int, flag: string, repoid: int|null, firstSubmissionDate: string, acceptanceDate: string|null, refusalDate: string|null, publicationDate: string|null, category: string}
     */
    private static function submission(int $docId, string $submitted, string $category, ?string $decided = null, int $status = 0, ?int $repoId = 1, ?int $paperId = null): array
    {
        return [
            'rvid' => 8,
            'paperid' => $paperId ?? $docId,
            'docid' => $docId,
            'status' => $status,
            'flag' => $category === SubmissionFigures::IMPORTED ? 'imported' : 'submitted',
            'repoid' => $repoId,
            'firstSubmissionDate' => $submitted . '-03-01 10:00:00',
            'acceptanceDate' => $category === SubmissionFigures::ACCEPTED && $decided !== null ? $decided . '-06-01 10:00:00' : null,
            'refusalDate' => $category === SubmissionFigures::REFUSED && $decided !== null ? $decided . '-06-01 10:00:00' : null,
            'publicationDate' => null,
            'category' => $category,
        ];
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

    /**
     * /journals/stats/{code}: the journal is looked up among the active journals only (strict mode),
     * so that a disabled journal returns a 404, as an unknown one.
     */
    public function testGetJournalOnlyLooksUpTheActiveJournals(): void
    {
        $this->reviewRepo
            ->expects($this->once())
            ->method('getJournalByIdentifier')
            ->with('disabled', true)
            ->willReturn(null);

        $this->assertNull($this->stats->getJournal(['code' => 'disabled']));
    }

    // -------------------------------------------------------------------------
    // getDashboard()
    // -------------------------------------------------------------------------

    /**
     * No submission (e.g. a year without any): no value (404), and the requested filters remain available ones
     * (not a 422 "Not available. The available parameters are: []").
     */
    public function testDashboardWithoutSubmissionIsNotFoundWithItsFilters(): void
    {
        $this->papersRepo->method('submissionsQuery')->willReturn($this->makeScalarQb(0));

        $dashboard = $this->stats->getDashboard(['filters' => ['year' => '2014']], ['is' => ['rvid' => 8, 'submissionDate' => [2014]]]);

        $this->assertSame('dashboard', $dashboard->getName());
        $this->assertNull($dashboard->getValue());
        $this->assertSame(ReviewStatsDataProvider::AVAILABLE_FILTERS, $dashboard->getAvailableFilters());

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ViewEvent($kernel, Request::create('/api/journals/stats/dashboard/x'), HttpKernelInterface::MAIN_REQUEST, $dashboard);

        $this->expectException(ResourceNotFoundException::class);
        new StatResourceSubscriber($this->em)->checkStatResourceAvailability($event);
    }

    /**
     * The figures count submissions: a refused paper submitted again (same PAPERID) is two submissions, and each
     * submission is counted in a single category, so that submissions = imported + accepted + refused + other.
     */
    public function testDashboardFiguresAddUpToTheSubmissions(): void
    {
        $this->papersRepo->method('getSubmissions')->willReturn([
            // the same paper: refused, then submitted again and published
            self::submission(10, '2023', SubmissionFigures::REFUSED, '2023', Paper::STATUS_REFUSED, paperId: 10),
            self::submission(11, '2024', SubmissionFigures::ACCEPTED, '2024', Paper::STATUS_PUBLISHED, paperId: 10),
            // accepted, then refused: refused only
            self::submission(20, '2024', SubmissionFigures::REFUSED, '2025', Paper::STATUS_REFUSED),
            self::submission(30, '2024', SubmissionFigures::ACCEPTED, '2025', Paper::STATUS_STRICTLY_ACCEPTED),
            self::submission(40, '2025', SubmissionFigures::OTHER),
            self::submission(50, '2025', SubmissionFigures::IMPORTED, status: Paper::STATUS_PUBLISHED),
        ]);
        $this->papersRepo->method('submissionsQuery')->willReturn($this->makeScalarQb(1));
        $this->papersRepo->method('getAvailableRepositories')->willReturn([]);
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);
        $this->paperLogRepo->method('getPublished')->willReturn(1);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);
        $this->paperLogRepo->method('getAcceptanceRate')->willReturnCallback(
            static fn(array $options): float => round($options['acceptedTotal'] / ($options['acceptedTotal'] + $options['refusedTotal']) * 100, 2)
        );
        $this->userRepo->method('findByReviewQuery')->willReturn($this->makeUserQb([], 0));

        $values = $this->stats->getDashboard([], ['is' => ['rvid' => '8']])->getValue();

        $this->assertSame(6, $values['nbSubmissions']);
        $this->assertSame(1, $values['nbImported']);
        $this->assertSame(2, $values['nbAccepted']);
        $this->assertSame(2, $values['nbRefused']);
        $this->assertSame(1, $values['nbOtherStatus']);
        $this->assertSame(1, $values['nbAcceptedNotYetPublished']);
        $this->assertSame($values['nbSubmissions'], $values['nbImported'] + $values['nbAccepted'] + $values['nbRefused'] + $values['nbOtherStatus']);
        $this->assertSame(50.0, $values['rates']['acceptance']);

        // by year: accepted and refused by year of the decision, the others by year of submission
        $byYear = $this->stats->getDashboard([], ['is' => ['rvid' => '8', 'submissionDate' => [2025]]])->getValue();

        $this->assertSame(2, $byYear['nbSubmissions']);
        $this->assertSame(1, $byYear['nbAccepted']);
        $this->assertSame(1, $byYear['nbRefused']);
        $this->assertSame(1, $byYear['nbOtherStatus']);
    }

    // -------------------------------------------------------------------------
    // getDashboardPaperIds()
    // -------------------------------------------------------------------------

    /**
     * withPapers: the papers of each figure of the dashboard, with the filters of the figures (year: submissionDate).
     * The figures counted by submission give the version each submission ends with; the others the PAPERID.
     */
    public function testDashboardPaperIdsAreComputedForEachFigure(): void
    {
        $filters = ['is' => ['rvid' => '8', 'submissionDate' => '2025']];

        $this->papersRepo->expects($this->once())->method('getSubmissions')->with(8, null)->willReturn([
            self::submission(10, '2025', SubmissionFigures::ACCEPTED, '2025', Paper::STATUS_PUBLISHED, paperId: 1),
            self::submission(20, '2025', SubmissionFigures::ACCEPTED, '2025', Paper::STATUS_STRICTLY_ACCEPTED, paperId: 2),
            self::submission(30, '2025', SubmissionFigures::OTHER, paperId: 3),
            // submitted in 2024, refused in 2025: a refusal of 2025, not a submission of 2025
            self::submission(40, '2024', SubmissionFigures::REFUSED, '2025', Paper::STATUS_REFUSED, paperId: 4),
            self::submission(50, '2025', SubmissionFigures::IMPORTED, status: Paper::STATUS_PUBLISHED, paperId: 5),
        ]);
        $this->papersRepo->method('getSubmissionPaperIds')->willReturnCallback(
            static fn(array $f, ?string $flag = null, string $date = 'submissionDate'): array => $date === 'publicationDate' ? [5] : []
        );
        $this->paperLogRepo->method('getPaperIds')->willReturnCallback(static fn(string $figure): array => $figure === 'published' ? [1] : []);
        $this->paperLogRepo->method('getSubmissionDelayPaperIds')->willReturnCallback(
            static fn(int $rvId, ?array $years, ?string $start, string $operation): array => $operation === 'median-submission-publication' ? [1] : [1, 2]
        );

        $this->assertSame([
            'nbSubmissions' => [['paperid' => 1, 'docid' => 10], ['paperid' => 2, 'docid' => 20], ['paperid' => 3, 'docid' => 30], ['paperid' => 5, 'docid' => 50]],
            'nbImported' => [['paperid' => 5, 'docid' => 50]],
            'nbImportedPublished' => [5],
            'nbPublished' => [1],
            'nbAccepted' => [['paperid' => 1, 'docid' => 10], ['paperid' => 2, 'docid' => 20]],
            'nbRefused' => [['paperid' => 4, 'docid' => 40]],
            'nbAcceptedNotYetPublished' => [['paperid' => 2, 'docid' => 20]],
            'nbOtherStatus' => [['paperid' => 3, 'docid' => 30]],
            'submissionAcceptanceTime' => [1, 2],
            'submissionPublicationTime' => [1],
        ], $this->stats->getDashboardPaperIds($filters));
    }

    public function testDashboardPaperIdsWithoutImportedPublishedWithAStartDateOnly(): void
    {
        $this->papersRepo->method('getSubmissionPaperIds')->willReturn([]);
        $this->papersRepo->method('getSubmissionsAwaitingDecisionPaperIds')->willReturn([]);
        $this->paperLogRepo->method('getPaperIds')->willReturn([]);
        $this->paperLogRepo->method('getSubmissionDelayPaperIds')->willReturn([]);

        $papers = $this->stats->getDashboardPaperIds(['is' => ['rvid' => '8', 'startAfterDate' => '2025-01-01']]);

        $this->assertArrayNotHasKey('nbImportedPublished', $papers); // as in getDashboard()
    }

    // -------------------------------------------------------------------------
    // getNbPapersByStatus()
    // -------------------------------------------------------------------------

    public function testGetNbPapersByStatusReturnsEmptyArrayOnADatabaseError(): void
    {
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn(null);

        $this->assertSame([], $this->stats->getNbPapersByStatus());
    }

    public function testGetNbPapersByStatusWithNoRvIdGivesTheRowsOfAllTheJournals(): void
    {
        $this->paperLogRepo->expects($this->once())
            ->method('getSubmittedSameYear')
            ->with(Paper::STATUS_STRICTLY_ACCEPTED, null)
            ->willReturn([
                ['rvid' => 5, 'year' => 2022, 'total' => '10'],
                ['rvid' => 8, 'year' => 2023, 'total' => '3'],
            ]);

        $this->assertSame([
            ['rvid' => 5, 'year' => 2022, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 10],
            ['rvid' => 8, 'year' => 2023, Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 3],
        ], $this->stats->getNbPapersByStatus(null));
    }

    public function testGetNbPapersByStatusReformatsByYearWhenRvIdSet(): void
    {
        // the query only gives the rows of the journal
        $this->paperLogRepo->expects($this->once())
            ->method('getSubmittedSameYear')
            ->with(Paper::STATUS_STRICTLY_ACCEPTED, 8)
            ->willReturn([
                ['rvid' => 8, 'year' => 2022, 'total' => 5],
                ['rvid' => 8, 'year' => 2023, 'total' => 7],
            ]);

        $result = $this->stats->getNbPapersByStatus(8);

        $this->assertSame(['acceptedSubmittedSameYear' => 5], $result[8][2022]);
        $this->assertSame(['acceptedSubmittedSameYear' => 7], $result[8][2023]);
    }

    public function testGetNbPapersByStatusReturnsFilteredEmptyWhenRvIdHasNoRow(): void
    {
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);

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
        $this->papersRepo->expects($this->once())->method('getSubmissions')->with(null, null)->willReturn([
            self::submission(1, '2024', SubmissionFigures::OTHER),
            self::submission(2, '2025', SubmissionFigures::REFUSED, '2025', Paper::STATUS_REFUSED),
            self::submission(3, '2025', SubmissionFigures::IMPORTED),
        ]);

        $result = $this->stats->getSubmissionsStat(['is' => ['rvid' => null]]);

        $this->assertInstanceOf(SubmissionOutput::class, $result);
        $this->assertSame('nbSubmissions', $result->getName());
        $this->assertSame(3, $result->getValue());
    }

    public function testGetSubmissionsStatWithoutDetailsReturnsEmptyDetailsArray(): void
    {
        $this->papersRepo->method('getSubmissions')->willReturn([self::submission(1, '2024', SubmissionFigures::OTHER)]);

        $result = $this->stats->getSubmissionsStat(['is' => []]);

        $this->assertSame(1, $result->getValue());
        $this->assertIsArray($result->getDetails());
        $this->assertEmpty($result->getDetails());
    }

    /**
     * A refused paper submitted again is a new submission with the same PAPERID: both are counted, as in the list of
     * papers of the journal; the year, status and flag filters apply to each submission.
     */
    public function testGetSubmissionsStatCountsEachSubmissionOfAPaper(): void
    {
        $this->papersRepo->method('getSubmissions')->willReturn([
            self::submission(10, '2024', SubmissionFigures::REFUSED, '2024', Paper::STATUS_REFUSED, paperId: 10),
            self::submission(20, '2025', SubmissionFigures::OTHER, paperId: 10),
            self::submission(30, '2025', SubmissionFigures::IMPORTED, status: Paper::STATUS_PUBLISHED),
        ]);

        $this->assertSame(3, $this->stats->getSubmissionsStat(['is' => ['rvid' => '8']])->getValue());
        $this->assertSame(2, $this->stats->getSubmissionsStat(['is' => ['rvid' => '8', AppConstants::SUBMISSION_DATE => [2025]]])->getValue());
        $this->assertSame(1, $this->stats->getSubmissionsStat(['is' => ['rvid' => '8', AppConstants::PAPER_STATUS => (string)Paper::STATUS_REFUSED]])->getValue());
        $this->assertSame(1, $this->stats->getSubmissionsStat(['is' => ['rvid' => '8', AppConstants::PAPER_FLAG => 'imported']])->getValue());
    }

    // -------------------------------------------------------------------------
    // getDelayBetweenSubmissionAndLatestStatus()
    // -------------------------------------------------------------------------

    public function testGetDelayReturnsAcceptanceDelayOutputByDefault(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        $this->assertInstanceOf(SubmissionAcceptanceDelayOutput::class, $result);
        $this->assertSame('submissionAcceptanceTime', $result->getName());
    }

    public function testGetDelayReturnsPublicationDelayOutputForPublishedStatus(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => []],
            Paper::STATUS_PUBLISHED
        );

        $this->assertInstanceOf(SubmissionPublicationDelayOutput::class, $result);
        $this->assertSame('submissionPublicationTime', $result->getName());
    }

    public function testGetDelayDefaultUnitIsDay(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        $this->assertSame('DAY', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayAcceptsMonthUnitFromFilter(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_UNIT => 'month']]
        );

        $this->assertSame('MONTH', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayIgnoresInvalidUnitFromFilterAndKeepsParameterUnit(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        // Invalid filter unit → stays at parameter-level default ('day' → 'DAY')
        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_UNIT => 'nanosecond']]
        );

        $this->assertSame('DAY', $result->getValue()[Stats::STATS_UNIT]);
    }

    public function testGetDelayWithInvalidParameterUnitNormalizesToWeek(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

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
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => []],
            Paper::STATUS_STRICTLY_ACCEPTED,
            Stats::MEDIAN_METHOD
        );

        $this->assertStringContainsString('Median', $result->getName());
    }

    public function testGetDelayComputesAverageOverAllRows(): void
    {
        // the delay of each article, of the same journal and year
        $rows = [
            ['year' => 2022, 'rvid' => 5, 'delay' => 10],
            ['year' => 2022, 'rvid' => 5, 'delay' => 20],
            ['year' => 2022, 'rvid' => 5, 'delay' => 30],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []]);

        // avg(10, 20, 30) = 20 (rounded to 0 precision)
        $this->assertEquals(20, $result->getValue()['value']);
    }

    public function testGetDelayReturnsNullValueWhenNoRows(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $this->assertNull($this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => []])->getValue()['value']);
    }

    public function testGetDelayWithYearFilterAveragesOnlyMatchingYearRows(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 7.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 22.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => 2022]]
        );

        // avg(14, 22) = 18 (rounded to 0 precision)
        $this->assertEquals(18, $result->getValue()['value']);
    }

    public function testGetDelayWithYearFilterAsArrayAveragesOnlyMatchingYearRows(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 7.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 22.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => [2022]]]
        );

        // avg(14, 22) = 18 (rounded to 0 precision)
        $this->assertEquals(18, $result->getValue()['value']);
    }

    public function testGetDelayWithMultipleYearFilterAsArrayAveragesAllRequestedYears(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 7.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 22.0],
            ['rvid' => 8, 'year' => 2023, 'delay' => 5.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => [2022, 2023]]]
        );

        // avg(14, 7, 22, 5) = 12 (rounded to 0 precision)
        $this->assertEquals(12, $result->getValue()['value']);
    }

    public function testGetDelayWithRvIdAndYearFilterAsArrayDoesNotCrashAndAverages(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 20.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 99.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => [2022], 'rvid' => 5]]
        );

        // only year 2022 for rvid 5 -> avg(14) = 14
        $this->assertEquals(14, $result->getValue()['value']);
    }

    public function testGetDelayWithRvIdAndMultipleYearFilterAsArrayAveragesAcrossYear(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 14.0],
            ['rvid' => 5, 'year' => 2023, 'delay' => 20.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 99.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['submissionDate' => [2022, 2023], 'rvid' => 5]]
        );

        // avg(14, 20) = 17 for rvid 5
        $this->assertEquals(17, $result->getValue()['value']);
    }

    public function testGetDelayWithRvIdFilterAveragesOnlyMatchingRvIdRows(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2022, 'delay' => 10.0],
            ['rvid' => 8, 'year' => 2022, 'delay' => 40.0],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['rvid' => 5]]
        );

        $this->assertEquals(10, $result->getValue()['value']);
    }

    /**
     * 1 article at 10 days in 2023, 3 articles at 100 days in 2024: the mean of the articles (77.5, rounded to 78),
     * not the mean of the yearly averages (55), as the median is computed from the articles.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function averageOfTheArticlesProvider(): array
    {
        return [
            'journal, all the years' => [['rvid' => 5]],
            'journal, several years' => [['rvid' => 5, 'submissionDate' => [2023, 2024]]],
            'platform, several years' => [['submissionDate' => [2023, 2024]]],
            'platform, all the years' => [[]],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    #[DataProvider('averageOfTheArticlesProvider')]
    public function testGetDelayAverageIsTheMeanOfTheArticlesNotOfTheYearlyAverages(array $filters): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([
            ['rvid' => 5, 'year' => 2023, 'delay' => 10],
            ['rvid' => 5, 'year' => 2024, 'delay' => 100],
            ['rvid' => 5, 'year' => 2024, 'delay' => 100],
            ['rvid' => 5, 'year' => 2024, 'delay' => 100],
        ]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => $filters]);

        $this->assertEquals(78, $result->getValue()['value']);
    }

    public function testGetDelayAverageOfThePlatformGivesTheSameWeightToEachArticleOfEachJournal(): void
    {
        // journal 5: 1 article at 10 days; journal 8: 3 articles at 100 days, the same year
        $this->paperLogRepo->method('getArticleDelays')->willReturn([
            ['rvid' => 5, 'year' => 2024, 'delay' => 10],
            ['rvid' => 8, 'year' => 2024, 'delay' => 100],
            ['rvid' => 8, 'year' => 2024, 'delay' => 100],
            ['rvid' => 8, 'year' => 2024, 'delay' => 100],
        ]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => ['submissionDate' => 2024]]);

        $this->assertEquals(78, $result->getValue()['value']);
    }

    public function testGetDelayAverageKeepsTheAverageOfEachYearInTheDetails(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([
            ['rvid' => 5, 'year' => 2023, 'delay' => 10],
            ['rvid' => 5, 'year' => 2024, 'delay' => 100],
            ['rvid' => 5, 'year' => 2024, 'delay' => 101],
        ]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(['is' => ['rvid' => 5, AppConstants::WITH_DETAILS => true]]);

        // value: avg(10, 100, 101) = 70.33 → 70; details: 10 for 2023, avg(100, 101) = 100.5 → 101 for 2024
        $this->assertEquals(70, $result->getValue()['value']);
        $this->assertEquals(10, $result->getDetails()[2023]['delay']['value']);
        $this->assertEquals(101, $result->getDetails()[2024]['delay']['value']);
        $this->assertSame(2, $result->getDetails()[2024]['delay']['count']);
    }

    public function testArticleAverageDelayIsNullWithoutAnyArticleOfTheJournalAndYears(): void
    {
        $rows = [['rvid' => 5, 'year' => 2023, 'delay' => 10], ['rvid' => 8, 'year' => 2024, 'delay' => null]];

        $this->assertNull(Stats::articleAverageDelay($rows, 8));
        $this->assertNull(Stats::articleAverageDelay($rows, 5, [2024]));
        $this->assertNull(Stats::articleAverageDelay([]));
        $this->assertEquals(10, Stats::articleAverageDelay($rows, 5, ['2023']));
    }

    public function testGetDelayMethodCanBeOverriddenFromFilter(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => [Stats::STATS_METHOD => 'median']]
        );

        $this->assertSame('median', $result->getValue()[Stats::STATS_METHOD]);
    }

    public function testGetDelayMedianWithDetailsGivesTheMedianOfEachYear(): void
    {
        $rows = [
            ['rvid' => 5, 'year' => 2023, 'delay' => '30'],
            ['rvid' => 5, 'year' => 2022, 'delay' => '10'],
            ['rvid' => 5, 'year' => 2022, 'delay' => '40'],
            ['rvid' => 5, 'year' => 2022, 'delay' => '20'],
            ['rvid' => 5, 'year' => 2023, 'delay' => '15'],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['rvid' => 5, AppConstants::WITH_DETAILS => true]],
            Paper::STATUS_STRICTLY_ACCEPTED,
            Stats::MEDIAN_METHOD
        );

        $details = $result->getDetails();

        // the years in ascending order: median(10, 20, 40) = 20, median(15, 30) = 22.5, rounded to 23
        $this->assertSame([2022, 2023], array_keys($details));
        $this->assertEquals(20, $details[2022]['delay']['value']);
        $this->assertEquals(23, $details[2023]['delay']['value']);
        $this->assertSame('DAY', $details[2022]['delay'][Stats::STATS_UNIT]);
        $this->assertSame(Stats::MEDIAN_METHOD, $details[2022]['delay'][Stats::STATS_METHOD]);
        $this->assertSame(3, $details[2022]['delay']['count']);
        $this->assertSame(2, $details[2023]['delay']['count']);
    }

    public function testGetDelayAverageWithDetailsGivesTheNumberOfArticlesOfEachYear(): void
    {
        // the delay of each article
        $rows = [
            ['year' => 2022, 'rvid' => 5, 'delay' => '10'],
            ['year' => 2022, 'rvid' => 5, 'delay' => '18'],
            ['year' => 2022, 'rvid' => 5, 'delay' => '26'],
            ['year' => 2023, 'rvid' => 5, 'delay' => '7'],
        ];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

        $details = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['rvid' => 5, AppConstants::WITH_DETAILS => true]]
        )->getDetails();

        $this->assertEquals(18, $details[2022]['delay']['value']);
        $this->assertSame(3, $details[2022]['delay']['count']);
        $this->assertSame(1, $details[2023]['delay']['count']);
    }

    public function testGetDelayFromTheArticleDelaysDoesNotQuery(): void
    {
        $this->paperLogRepo->expects($this->never())->method('getArticleDelays');

        $articleDelays = [
            ['year' => '2022', 'rvid' => '5', 'delay' => '10'],
            ['year' => '2022', 'rvid' => '5', 'delay' => '15'],
            ['year' => '2023', 'rvid' => '5', 'delay' => '40'],
        ];
        $filters = ['is' => ['rvid' => 5, AppConstants::WITH_DETAILS => true]];

        $average = $this->stats->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_STRICTLY_ACCEPTED, Stats::DEFAULT_METHOD, 'day', $articleDelays);
        $median = $this->stats->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_STRICTLY_ACCEPTED, Stats::MEDIAN_METHOD, 'day', $articleDelays);

        // average of each year as the query: 12.5 rounded half away from zero, with the number of articles
        $this->assertEquals(13, $average->getDetails()['2022']['delay']['value']);
        $this->assertSame(2, $average->getDetails()['2022']['delay']['count']);
        $this->assertEquals(40, $average->getDetails()['2023']['delay']['value']);
        // global average: the mean of the articles, as the median (not the mean of the yearly averages, 27)
        $this->assertEquals(22, $average->getValue()['value']);
        $this->assertEquals(15, $median->getValue()['value']);
        $this->assertEquals(13, $median->getDetails()['2022']['delay']['value']);
    }

    public function testGetDelayFromTheGivenArticleDelaysGivesTheSameAverageAsFromTheQuery(): void
    {
        $articleDelays = [
            ['year' => '2022', 'rvid' => '5', 'delay' => '10'],
            ['year' => '2022', 'rvid' => '5', 'delay' => '15'],
            ['year' => '2023', 'rvid' => '5', 'delay' => '40'],
        ];
        $this->paperLogRepo->method('getArticleDelays')->willReturn($articleDelays);
        $filters = ['is' => ['rvid' => 5, 'submissionDate' => [2022, 2023], AppConstants::WITH_DETAILS => true]];

        $fromQuery = $this->stats->getDelayBetweenSubmissionAndLatestStatus($filters);
        $fromArticles = $this->stats->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_STRICTLY_ACCEPTED, Stats::DEFAULT_METHOD, 'day', $articleDelays);

        $this->assertEquals($fromQuery->getValue(), $fromArticles->getValue());
        $this->assertEquals($fromQuery->getDetails(), $fromArticles->getDetails());
    }

    public function testGetDelayMedianWithoutDetailsHasNoDetails(): void
    {
        $this->paperLogRepo->method('getArticleDelays')->willReturn([['rvid' => 5, 'year' => 2022, 'delay' => 10]]);

        $result = $this->stats->getDelayBetweenSubmissionAndLatestStatus(
            ['is' => ['rvid' => 5]],
            Paper::STATUS_STRICTLY_ACCEPTED,
            Stats::MEDIAN_METHOD
        );

        $this->assertSame([], $result->getDetails());
    }

    public function testGetDelayWithDetailsAndNoYearNorRvIdSetsDetailsToRawResult(): void
    {
        $rows = [['rvid' => 5, 'year' => 2022, 'delay' => 10.0]];

        $this->paperLogRepo->method('getArticleDelays')->willReturn($rows);

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

        $this->papersRepo->method('getAvailableRepositories')->willReturn([1]);
        $this->metadataSources->method('getLabel')->willReturnCallback(static fn(int $repoId): string => [1 => 'HAL', 2 => 'arXiv'][$repoId]);

        // fetched once, then counted by year
        $this->papersRepo->expects($this->once())->method('getSubmissions')->with(8, null)->willReturn([
            self::submission(1, '2023', SubmissionFigures::ACCEPTED, '2023', Paper::STATUS_PUBLISHED),
            self::submission(2, '2023', SubmissionFigures::ACCEPTED, '2023', Paper::STATUS_STRICTLY_ACCEPTED),
            self::submission(3, '2023', SubmissionFigures::REFUSED, '2023', Paper::STATUS_REFUSED),
            self::submission(4, '2023', SubmissionFigures::OTHER, repoId: null), // local versions only: no repository
            self::submission(5, '2023', SubmissionFigures::IMPORTED),
            // submitted in 2022, accepted in 2023: an acceptance of 2023, a submission of 2022
            self::submission(6, '2022', SubmissionFigures::ACCEPTED, '2023', Paper::STATUS_PUBLISHED, 2),
        ]);

        // PAPER_LOG counters are fetched once for all the years (not once per year)
        $this->paperLogRepo->expects($this->once())->method('getPublishedByYear')->with($rvId, [2022, 2023])->willReturn([2023 => 4]);
        $this->paperLogRepo->method('getAcceptanceRate')->willReturn(71.4);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]); // Simple case for getNbPapersByStatus

        $this->stats->getSubmissionByYearStats($filters, $rvId, $details);

        $this->assertSame([2022, 2023], array_keys($details[Stats::SUBMISSIONS_BY_YEAR]));
        $year = $details[Stats::SUBMISSIONS_BY_YEAR][2023];
        $this->assertSame(5, $year['submissions']);
        $this->assertSame(1, $year['imported']);
        $this->assertSame(4, $year['published']);
        $this->assertSame(3, $year['accepted']);
        $this->assertSame(1, $year['refused']);
        $this->assertSame(1, $year['acceptedNotYetPublished']);
        $this->assertSame(1, $year['others']);
        $this->assertSame(1, $details[Stats::SUBMISSIONS_BY_YEAR][2022]['submissions']);
        $this->assertSame(0, $details[Stats::SUBMISSIONS_BY_YEAR][2022]['accepted']);

        $this->assertSame(4, $details['submissionsByRepo'][2023]['HAL']['submissions']);
        $this->assertSame(0, $details['submissionsByRepo'][2022]['HAL']['submissions']);
        // the repository of submission 6, not returned by getAvailableRepositories(), is listed
        $this->assertSame(1, $details['submissionsByRepo'][2022]['arXiv']['submissions']);
        $this->assertSame(0, $details['submissionsByRepo'][2023]['arXiv']['submissions']);
    }

    /**
     * A year with decisions but no new submission is listed, so that the accepted and refused submissions by year
     * add up to their totals; the requested years still restrict the years
     */
    public function testGetSubmissionByYearStatsListsTheYearsOfDecision(): void
    {
        $this->papersRepo->method('getAvailableRepositories')->willReturn([]);
        $this->papersRepo->method('getSubmissions')->willReturn([
            self::submission(1, '2022', SubmissionFigures::ACCEPTED, '2024', Paper::STATUS_PUBLISHED),
            self::submission(2, '2022', SubmissionFigures::REFUSED, '2024', Paper::STATUS_REFUSED),
            self::submission(3, '2023', SubmissionFigures::OTHER),
        ]);
        $this->paperLogRepo->method('getPublishedByYear')->willReturn([]);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);
        $this->paperLogRepo->method('getAcceptanceRate')->willReturn(50.0);

        $details = [];
        $this->stats->getSubmissionByYearStats(['is' => ['rvid' => 8]], 8, $details);

        $this->assertSame([2022, 2023, 2024], array_keys($details[Stats::SUBMISSIONS_BY_YEAR]));
        $year = $details[Stats::SUBMISSIONS_BY_YEAR][2024];
        $this->assertSame(0, $year['submissions']);
        $this->assertSame(1, $year['accepted']);
        $this->assertSame(1, $year['refused']);
        $this->assertSame(0, $details[Stats::SUBMISSIONS_BY_YEAR][2022]['accepted']);

        $details = [];
        $this->stats->getSubmissionByYearStats(['is' => ['rvid' => 8, 'submissionDate' => ['2022', '2024']]], 8, $details);

        $this->assertSame([2022, 2024], array_keys($details[Stats::SUBMISSIONS_BY_YEAR]));
    }

    /**
     * A repository counted by the submissions is listed even if getAvailableRepositories() does not return it
     * (a resubmission may be the only one of its repository within the start date)
     */
    public function testGetSubmissionByYearStatsListsTheRepositoriesOfTheSubmissions(): void
    {
        $details = [];

        $this->papersRepo->method('getAvailableRepositories')->willReturn([1]);
        $this->metadataSources->method('getLabel')->willReturnCallback(static fn(int $repoId): string => [1 => 'HAL', 2 => 'arXiv'][$repoId]);
        $this->papersRepo->method('getSubmissions')->willReturn([
            self::submission(1, '2023', SubmissionFigures::OTHER),
            self::submission(2, '2023', SubmissionFigures::OTHER, repoId: 2, paperId: 1),
        ]);
        $this->paperLogRepo->method('getPublishedByYear')->willReturn([]);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);

        $this->stats->getSubmissionByYearStats(['is' => ['rvid' => 8, 'startAfterDate' => '2023-01-01']], 8, $details);

        $this->assertSame(['HAL' => ['submissions' => 1], 'arXiv' => ['submissions' => 1]], $details['submissionsByRepo'][2023]);
    }

    /**
     * Regression: when no journal is selected ($rvId = null), the "accepted submitted same year"
     * figure must be summed per year across all journals' raw rows, not keyed/indexed by $rvId
     * (the previous null-$rvId path indexed by $rvId, producing no per-year values).
     */
    public function testGetSubmissionByYearStatsSumsSubmittedSameYearPerYearWithNoRvId(): void
    {
        $filters = ['is' => []];
        $rvId = null;
        $details = [];

        $this->papersRepo->method('getAvailableRepositories')->willReturn([]);
        $this->papersRepo->method('getSubmissions')->willReturn([self::submission(1, '2022', SubmissionFigures::OTHER)]);
        $this->paperLogRepo->method('getPublishedByYear')->willReturn([2022 => 1]);
        $this->paperLogRepo->method('getAcceptanceRate')->willReturn(40.0);

        // Raw rows across multiple journals for the same year: must be summed per year.
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([
            ['rvid' => 5, 'year' => 2022, 'total' => 4],
            ['rvid' => 8, 'year' => 2022, 'total' => 6],
        ]);

        $this->stats->getSubmissionByYearStats($filters, $rvId, $details);

        $this->assertSame(
            10,
            $details[Stats::SUBMISSIONS_BY_YEAR][2022][Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR]
        );
    }

    public function testGetSubmissionByYearStatsCountsImportedSubmissionsByYear(): void
    {
        $filters = ['is' => ['rvid' => 8]];
        $details = [];

        $this->papersRepo->method('getAvailableRepositories')->willReturn([]);
        $this->papersRepo->method('getSubmissions')->willReturn([
            self::submission(1, '2022', SubmissionFigures::IMPORTED),
            self::submission(2, '2022', SubmissionFigures::IMPORTED),
            self::submission(3, '2022', SubmissionFigures::IMPORTED),
            self::submission(4, '2023', SubmissionFigures::OTHER),
        ]);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);

        $this->stats->getSubmissionByYearStats($filters, 8, $details);

        $this->assertSame(3, $details[Stats::SUBMISSIONS_BY_YEAR][2022]['imported']);
        $this->assertSame(0, $details[Stats::SUBMISSIONS_BY_YEAR][2023]['imported']);
    }

    /**
     * withDetails combined with a specific year: the details are those of that year
     */
    public function testGetSubmissionByYearStatsWithASingleYearFilter(): void
    {
        $filters = ['is' => ['rvid' => 8, AppConstants::SUBMISSION_DATE => 2023]];
        $details = [];

        $this->papersRepo->method('getAvailableRepositories')->willReturn([]);
        $this->papersRepo->method('getSubmissions')->willReturn([
            self::submission(1, '2022', SubmissionFigures::IMPORTED),
            self::submission(2, '2023', SubmissionFigures::IMPORTED),
            self::submission(3, '2023', SubmissionFigures::OTHER),
        ]);
        $this->paperLogRepo->method('getSubmittedSameYear')->willReturn([]);

        $this->stats->getSubmissionByYearStats($filters, 8, $details);

        $this->assertSame([2023], array_keys($details[Stats::SUBMISSIONS_BY_YEAR]));
        $this->assertSame(2, $details[Stats::SUBMISSIONS_BY_YEAR][2023]['submissions']);
        $this->assertSame(1, $details[Stats::SUBMISSIONS_BY_YEAR][2023]['imported']);
    }
}
