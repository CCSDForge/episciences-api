<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\ReviewerReport;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Exception\ResourceNotFoundException;
use App\Repository\PaperConflictsRepository;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\ReviewerReportRepository;
use App\Repository\UserInvitationRepository;
use App\Resource\Statistic;
use App\Service\StatisticPapers;
use App\Service\Stats;
use App\State\StatisticStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The evaluation statistics (3 indicators) requested without pagination are flagged
 * when capped, like the other collections (see TruncatedCollectionSubscriber).
 * The query parameters (indicator, startAfterDate, unit) are validated before any query.
 */
class StatisticStateProviderTest extends TestCase
{
    private const string EVALUATION_OPERATION = '_api_/statistics/evaluation_get_collection';
    private const string COLLECTION_OPERATION = '_api_/statistics/_get_collection';

    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testUnpaginatedIndicatorsAboveTheMaximumAreFlagged(): void
    {
        $this->provideEvaluation(pagination: 'false', maxItemsPerPage: 2);

        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testCompleteUnpaginatedIndicatorsAreNotFlagged(): void
    {
        $this->provideEvaluation(pagination: 'false', maxItemsPerPage: 30);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedIndicatorsAreNotFlagged(): void
    {
        $this->provideEvaluation(pagination: 'true', maxItemsPerPage: 2);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testIndicatorSentAsArrayIsAccepted(): void
    {
        $result = $this->provideEvaluation(filters: ['indicator' => ['reviews-requested']]);

        $statistics = iterator_to_array($result);
        $this->assertCount(1, $statistics);
        $this->assertSame('reviews-requested', $statistics[0]->getName());
    }

    public function testSeveralEvaluationIndicatorsAreReturnedInTheRequestedOrder(): void
    {
        $statistics = iterator_to_array($this->provideEvaluation(filters: ['indicator' => ['reviews-received', 'reviews-requested', 'reviews-received']]));

        $this->assertSame(['reviews-received', 'reviews-requested'], array_map(static fn(Statistic $statistic): string => $statistic->getName(), $statistics));
    }

    public function testSeveralIndicatorsAreReturnedInTheRequestedOrder(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['indicator' => ['median-submission-publication', 'evaluation'], 'unit' => 'day']));

        $this->assertSame(
            ['median-submission-publication', 'reviews-requested', 'reviews-received', 'median-reviews-number'],
            array_map(static fn(Statistic $statistic): string => $statistic->getName(), $statistics)
        );
    }

    public function testAnInvalidIndicatorAmongSeveralIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->provideCollection(['indicator' => ['median-submission-publication', 'foo']]);
    }

    /**
     * The repository returns one row by invitation (see EvaluationQueriesDatabaseTest): each row is a review requested.
     */
    public function testEachInvitationIsARequestedReview(): void
    {
        $invitations = [['rvid' => 1, 'id' => 7, 'status' => 'accepted'], ['rvid' => 1, 'id' => 8, 'status' => 'pending']];

        $statistics = iterator_to_array($this->provideEvaluation(filters: ['indicator' => ['reviews-requested']], invitations: $invitations));

        $this->assertSame(2.0, $statistics[0]->getValue());
    }

    /**
     * The median number of reviews is computed by paper, all its versions together: paper 1 has 4 reports
     * (2 on each of its versions), paper 2 has 1 report, paper 3 has 3 reports. By version, it would be 2.
     */
    public function testMedianNumberOfReviewsIsComputedByPaper(): void
    {
        $reports = [
            ['docid' => 1, 'paperid' => 1, 'count' => 1],
            ['docid' => 1, 'paperid' => 1, 'count' => 1],
            ['docid' => 2, 'paperid' => 1, 'count' => 1],
            ['docid' => 2, 'paperid' => 1, 'count' => 1],
            ['docid' => 3, 'paperid' => 2, 'count' => 1],
            ['docid' => 4, 'paperid' => 3, 'count' => 1],
            ['docid' => 4, 'paperid' => 3, 'count' => 1],
            ['docid' => 4, 'paperid' => 3, 'count' => 1],
        ];

        $statistics = iterator_to_array($this->provideEvaluation(filters: ['indicator' => ['reviews-received', 'median-reviews-number']], reports: $reports));

        $this->assertSame([8.0, 3.0], array_map(static fn(Statistic $statistic): ?float => $statistic->getValue(), $statistics));
    }

    public function testStartAfterDateFiltersTheEvaluation(): void
    {
        $repositoryOptions = [];

        iterator_to_array($this->provideEvaluation(filters: ['startAfterDate' => '2023-01-31'], repositoryOptions: $repositoryOptions));

        $this->assertSame('2023-01-31', $repositoryOptions['getReviewsRequested']['startAfterDate'] ?? null);
        $this->assertSame('2023-01-31', $repositoryOptions['getReceivedReports']['startAfterDate'] ?? null);
    }

    public function testValidStartAfterDateIsAccepted(): void
    {
        $result = $this->provideEvaluation(filters: ['startAfterDate' => '2023-01-31']);

        $this->assertCount(count(Statistic::EVAL_INDICATORS), iterator_to_array($result));
    }

    #[DataProvider('validUnitProvider')]
    public function testValidOrEmptyUnitIsAccepted(string $unit): void
    {
        $result = $this->provideEvaluation(filters: ['unit' => $unit]);

        $this->assertCount(count(Statistic::EVAL_INDICATORS), iterator_to_array($result));
    }

    public static function validUnitProvider(): iterable
    {
        yield 'empty (default unit)' => [''];
        yield 'day' => ['day'];
        yield 'upper case' => ['MONTH'];
    }

    #[DataProvider('invalidFilterProvider')]
    public function testInvalidFilterIsRejected(array $filters): void
    {
        $this->expectException(ValidationException::class);

        $this->provideEvaluation(filters: $filters);
    }

    public static function invalidFilterProvider(): iterable
    {
        yield 'not a date' => [['startAfterDate' => 'abc']];
        yield 'impossible date' => [['startAfterDate' => '2023-02-30']];
        yield 'incomplete date' => [['startAfterDate' => '2023-1-1']];
        yield 'date sent as array' => [['startAfterDate' => ['2023-01-01']]];
        yield 'unknown unit' => [['unit' => 'foo']];
        yield 'unit sent as array' => [['unit' => ['day']]];
        yield 'unknown indicator' => [['indicator' => 'foo']];
    }

    public function testMedianDelayIsReturnedWithTheNumberOfArticles(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['indicator' => 'median-submission-publication', 'unit' => 'day']));

        $this->assertCount(1, $statistics);
        $this->assertSame(317.0, $statistics[0]->getValue());
        $this->assertSame(1843, $statistics[0]->getCount());
        $this->assertNull($statistics[0]->getJournal());
    }

    /**
     * One item by journal, sorted by code; the logs of a journal that no longer exists are ignored.
     */
    public function testMedianDelayIsGroupedByJournal(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['indicator' => 'median-submission-publication', 'unit' => 'day', 'groupBy' => 'journal']));

        $this->assertSame(['dmtcs', 'jdmdh'], array_map(static fn(Statistic $statistic): ?string => $statistic->getJournal(), $statistics));
        $this->assertSame([335.0, 233.0], array_map(static fn(Statistic $statistic): float|array|null => $statistic->getValue(), $statistics));
        $this->assertSame([412, 57], array_map(static fn(Statistic $statistic): ?int => $statistic->getCount(), $statistics));
        $this->assertSame('day', $statistics[0]->getUnit());
    }

    /**
     * A disabled journal is looked up among the active journals only (strict mode): 404, as an unknown journal.
     */
    public function testDisabledJournalIsNotFound(): void
    {
        $reviewRepository = $this->createMock(ReviewRepository::class);
        $reviewRepository->expects($this->atLeastOnce())->method('getJournalByIdentifier')->with('disabled', true)->willReturn(null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([[Review::class, $reviewRepository]]);

        $provider = new StatisticStateProvider($entityManager, $this->createStub(LoggerInterface::class), new Pagination(['client_enabled' => true]), $this->createStub(Stats::class), new StatisticPapers($entityManager, $this->createStub(Security::class)));
        $operation = new GetCollection(class: Statistic::class, name: self::COLLECTION_OPERATION, paginationMaximumItemsPerPage: 30);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('not found Journal disabled');

        $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => 'true', 'rvcode' => 'disabled'], 'request' => $this->request]);
    }

    // ─── withPapers ──────────────────────────────────────────────────────────

    public function testPapersAreListedForAnAdministratorOfTheJournal(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => ['nb-submissions', 'acceptance-rate', 'median-submission-publication']], $this->administrator(1));

        // one DOCID by paper, its latest version
        $this->assertSame([101, 102, 103, 104], $statistics[0]->getPapers());
        $this->assertSame(0, $statistics[0]->getHiddenPapers());
        $this->assertSame(['accepted' => [102, 103], 'refused' => [104]], $statistics[1]->getPapers());
        $this->assertSame(['accepted' => 0, 'refused' => 0], $statistics[1]->getHiddenPapers());
        $this->assertSame([102, 103], $statistics[2]->getPapers());
    }

    /**
     * Conflicts enabled: the papers for which the administrator has declared a conflict are not listed, only counted.
     */
    public function testPapersWithADeclaredConflictAreHidden(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => ['nb-submissions', 'acceptance-rate']], $this->administrator(1), coiEnabled: true, conflicts: [3]);

        $this->assertSame([101, 102, 104], $statistics[0]->getPapers());
        $this->assertSame(1, $statistics[0]->getHiddenPapers());
        $this->assertSame(['accepted' => [102], 'refused' => [104]], $statistics[1]->getPapers());
        $this->assertSame(['accepted' => 1, 'refused' => 0], $statistics[1]->getHiddenPapers());
    }

    public function testDeclaredConflictsAreIgnoredWhenTheConflictsAreDisabled(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => 'nb-submissions'], $this->administrator(1), coiEnabled: false, conflicts: [3]);

        $this->assertSame([101, 102, 103, 104], $statistics[0]->getPapers());
    }

    /**
     * As in PapersVoter, the platform administrators are not subject to the conflicts.
     */
    public function testPlatformAdministratorIsNotSubjectToTheConflicts(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => 'nb-submissions'], null, epiAdmin: true, coiEnabled: true, conflicts: [3]);

        $this->assertSame([101, 102, 103, 104], $statistics[0]->getPapers());
        $this->assertSame(0, $statistics[0]->getHiddenPapers());
    }

    /**
     * The evaluation lists the reviews counted, without the reviewers: one item by invitation (with its answer; a pending
     * invitation past its expiration is expired) or by completed report, with the DOCID of the paper version.
     */
    public function testReviewsAreListedForAnAdministratorOfTheJournal(): void
    {
        $statistics = $this->provideWithPapers([], $this->administrator(1), operationName: self::EVALUATION_OPERATION);

        $this->assertSame(['reviews-requested', 'reviews-received', 'median-reviews-number'], array_map(static fn(Statistic $statistic): string => $statistic->getName(), $statistics));
        $this->assertSame([
            ['docid' => 201, 'date' => '2024-03-01', 'answer' => 'accepted'],
            ['docid' => 203, 'date' => '2024-05-02', 'answer' => 'declined'],
            ['docid' => 204, 'date' => '2024-07-03', 'answer' => 'expired'],
        ], $statistics[0]->getReviews());
        $this->assertSame(0, $statistics[0]->getHiddenReviews());
        $this->assertSame([['docid' => 201, 'date' => '2024-03-20'], ['docid' => 203, 'date' => '2024-05-20']], $statistics[1]->getReviews());
        // the median is not a number of reviews: no list
        $this->assertNull($statistics[2]->getReviews());
        $this->assertNull($statistics[2]->getPapers());
    }

    public function testReviewsOfAPaperWithADeclaredConflictAreHidden(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => ['reviews-requested', 'reviews-received']], $this->administrator(1), coiEnabled: true, conflicts: [3], operationName: self::EVALUATION_OPERATION);

        $this->assertSame([201, 204], array_column($statistics[0]->getReviews(), 'docid'));
        $this->assertSame(1, $statistics[0]->getHiddenReviews());
        $this->assertSame([201], array_column($statistics[1]->getReviews(), 'docid'));
        $this->assertSame(1, $statistics[1]->getHiddenReviews());
    }

    public function testReviewsAreDeniedToTheOtherUsers(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->provideWithPapers([], new User()->setUid(7)->setCurrentJournalID(1), administrator: false, operationName: self::EVALUATION_OPERATION);
    }

    /**
     * On /api/statistics/, indicator=evaluation returns the three statistics of the evaluation: the same reviews as on
     * /api/statistics/evaluation.
     */
    public function testReviewsAreListedWithTheEvaluationIndicatorOfTheCollection(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => 'evaluation'], $this->administrator(1));

        $this->assertSame(['reviews-requested', 'reviews-received', 'median-reviews-number'], array_map(static fn(Statistic $statistic): string => $statistic->getName(), $statistics));
        $this->assertSame([201, 203, 204], array_column($statistics[0]->getReviews(), 'docid'));
        $this->assertSame([201, 203], array_column($statistics[1]->getReviews(), 'docid'));
        $this->assertNull($statistics[2]->getReviews());
    }

    /**
     * Only a pending invitation expires: an answered invitation keeps its answer once its expiration date is past.
     */
    public function testOnlyAPendingInvitationPastItsExpirationIsExpired(): void
    {
        $invitation = static fn(int $id, string $status, ?string $expiration): array => [
            'id' => $id, 'itemid' => 200 + $id, 'paperid' => $id, 'status' => $status,
            'date' => new \DateTime('2024-03-01 10:00:00'), 'expirationDate' => $expiration === null ? null : new \DateTime($expiration),
        ];

        $statistics = $this->provideWithPapers(['indicator' => 'reviews-requested'], $this->administrator(1), operationName: self::EVALUATION_OPERATION, invitations: [
            $invitation(1, 'accepted', '2024-04-01'),
            $invitation(2, 'declined', '2024-04-01'),
            $invitation(3, 'pending', '2024-04-01'),
            $invitation(4, 'pending', '+1 month'),
            $invitation(5, 'pending', null),
        ]);

        $this->assertSame(['accepted', 'declined', 'expired', 'pending', 'pending'], array_column($statistics[0]->getReviews(), 'answer'));
    }

    /**
     * As for the papers, the platform administrators are not subject to the conflicts.
     */
    public function testPlatformAdministratorSeesAllTheReviews(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => ['reviews-requested', 'reviews-received']], null, epiAdmin: true, coiEnabled: true, conflicts: [3], operationName: self::EVALUATION_OPERATION);

        $this->assertSame([201, 203, 204], array_column($statistics[0]->getReviews(), 'docid'));
        $this->assertSame(0, $statistics[0]->getHiddenReviews());
        $this->assertSame([201, 203], array_column($statistics[1]->getReviews(), 'docid'));
        $this->assertSame(0, $statistics[1]->getHiddenReviews());
    }

    /**
     * The lists reuse the rows of the figures: each query of the evaluation runs once.
     */
    public function testTheEvaluationQueriesRunOnceWithTheLists(): void
    {
        $queries = [];
        $this->provideWithPapers([], $this->administrator(1), operationName: self::EVALUATION_OPERATION, queries: $queries);

        $this->assertSame(['getReviewsRequested' => 1, 'getReceivedReports' => 1], $queries);
    }

    public function testReviewsAreSortedByDocIdThenDate(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => 'reviews-received'], $this->administrator(1), operationName: self::EVALUATION_OPERATION, reports: [
            ['docid' => 203, 'paperid' => 3, 'count' => 1, 'date' => '2024-05-20 10:00:00'],
            ['docid' => 201, 'paperid' => 1, 'count' => 1, 'date' => '2024-03-21 10:00:00'],
            ['docid' => 201, 'paperid' => 1, 'count' => 1, 'date' => '2024-03-20 10:00:00'],
        ]);

        $this->assertSame([
            ['docid' => 201, 'date' => '2024-03-20'],
            ['docid' => 201, 'date' => '2024-03-21'],
            ['docid' => 203, 'date' => '2024-05-20'],
        ], $statistics[0]->getReviews());
    }

    /**
     * A review whose paper is unknown cannot be checked against the conflicts: it is counted, not listed.
     */
    public function testAReviewWithoutPaperIsNotListed(): void
    {
        $statistics = $this->provideWithPapers(['indicator' => 'reviews-received'], $this->administrator(1), operationName: self::EVALUATION_OPERATION, reports: [
            ['docid' => 201, 'paperid' => 1, 'count' => 1, 'date' => '2024-03-20 10:00:00'],
            ['docid' => 205, 'count' => 1, 'date' => '2024-03-21 10:00:00'],
        ]);

        $this->assertSame([201], array_column($statistics[0]->getReviews(), 'docid'));
        $this->assertSame(1, $statistics[0]->getHiddenReviews());
    }

    public function testWithPapersErrorNamesBothRoutes(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('withPapers: Only available on /api/statistics/ and /api/statistics/evaluation');

        $this->provideWithPapers(['rvcode' => null], $this->administrator(1), operationName: self::EVALUATION_OPERATION);
    }

    public function testNoReviewsWithoutWithPapers(): void
    {
        $statistics = iterator_to_array($this->provideEvaluation(filters: ['indicator' => ['reviews-requested']]));

        $this->assertNull($statistics[0]->getReviews());
        $this->assertNull($statistics[0]->getHiddenReviews());
    }

    public function testNoPapersWithoutWithPapers(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['indicator' => 'nb-submissions']));

        $this->assertNull($statistics[0]->getPapers());
        $this->assertNull($statistics[0]->getHiddenPapers());
    }

    #[DataProvider('deniedUserProvider')]
    public function testPapersAreDeniedToTheOtherUsers(?User $user, bool $administrator): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->provideWithPapers(['indicator' => 'nb-submissions'], $user, administrator: $administrator);
    }

    public static function deniedUserProvider(): iterable
    {
        yield 'anonymous' => [null, false];
        yield 'not an administrator' => [new User()->setUid(7)->setCurrentJournalID(1), false];
        yield 'administrator of another journal' => [new User()->setUid(7)->setCurrentJournalID(2), true];
    }

    #[DataProvider('invalidWithPapersProvider')]
    public function testInvalidWithPapersIsRejected(array $filters, string $operationName): void
    {
        $this->expectException(ValidationException::class);

        $this->provideWithPapers($filters, $this->administrator(1), operationName: $operationName);
    }

    public static function invalidWithPapersProvider(): iterable
    {
        foreach (['collection' => self::COLLECTION_OPERATION, 'evaluation' => self::EVALUATION_OPERATION] as $label => $operationName) {
            yield "$label: not a boolean" => [['withPapers' => 'abc'], $operationName];
            yield "$label: without rvcode" => [['withPapers' => 'true', 'rvcode' => null], $operationName];
            // groupBy=journal is not combined with rvcode, which withPapers needs
            yield "$label: with groupBy" => [['groupBy' => 'journal'], $operationName];
            yield "$label: with groupBy, without rvcode" => [['groupBy' => 'journal', 'rvcode' => null], $operationName];
        }
    }

    private function administrator(int $journal): User
    {
        return new User()->setUid(7)->setCurrentJournalID($journal);
    }

    /**
     * @param int[] $conflicts papers for which the user has declared a conflict
     * @param array|null $invitations rows of getReviewsRequested(), the default ones if null
     * @param array|null $reports rows of getReceivedReports(), the default ones if null
     * @param array<string, int> $queries number of calls of getReviewsRequested() and getReceivedReports()
     * @return Statistic[]
     */
    private function provideWithPapers(array $filters, ?User $user, bool $administrator = true, bool $epiAdmin = false, bool $coiEnabled = false, array $conflicts = [], string $operationName = self::COLLECTION_OPERATION, ?array $invitations = null, ?array $reports = null, array &$queries = []): array
    {
        $journal = $this->createStub(Review::class);
        $journal->method('getRvid')->willReturn(1);
        $journal->method('getCode')->willReturn('dmtcs');
        $journal->method('getSetting')->willReturnCallback(static fn(string $key): ?string => $key === 'isCoiEnabled' && $coiEnabled ? '1' : null);

        $reviewRepository = $this->createStub(ReviewRepository::class);
        $reviewRepository->method('getJournalByIdentifier')->willReturn($journal);

        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);
        $papersRepository->method('getSubmissionPaperIds')->willReturn([1, 2, 3, 4]);
        // DOCID of the latest version of paper N: 100 + N
        $papersRepository->method('getLatestDocIds')->willReturnCallback(static fn(array $paperIds): array => array_combine($paperIds, array_map(static fn(int $id): int => 100 + $id, $paperIds)));
        $papersRepository->method('submissionsQuery')->willReturn($this->createQueryBuilder([], 4));

        $paperLogRepository = $this->createStub(PaperLogRepository::class);
        $paperLogRepository->method('getSubmissionMedianTimeDetails')->willReturn(['median' => 317.0, 'count' => 2]);
        $paperLogRepository->method('getSubmissionDelayPaperIds')->willReturn([2, 3]);
        $paperLogRepository->method('getPaperIds')->willReturnCallback(static fn(string $figure): array => $figure === 'accepted' ? [2, 3] : [4]);

        $paperConflictsRepository = $this->createStub(PaperConflictsRepository::class);
        $paperConflictsRepository->method('findBy')->willReturn(array_map(
            fn(int $paperId): PaperConflicts => $this->createConfiguredStub(PaperConflicts::class, ['getPaperId' => $paperId]),
            $conflicts
        ));

        // invitations: one row by invitation; invitation 3 is still pending, expired
        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        $queries = ['getReviewsRequested' => 0, 'getReceivedReports' => 0];
        $userInvitationRepository->method('getReviewsRequested')->willReturnCallback(function () use (&$queries, $invitations): QueryBuilder {
            $queries['getReviewsRequested']++;

            return $this->createQueryBuilder($invitations ?? [
            ['id' => 1, 'itemid' => 201, 'paperid' => 1, 'status' => 'accepted', 'date' => new \DateTime('2024-03-01 10:00:00'), 'expirationDate' => new \DateTime('2024-04-01')],
            ['id' => 2, 'itemid' => 203, 'paperid' => 3, 'status' => 'declined', 'date' => new \DateTime('2024-05-02 10:00:00'), 'expirationDate' => new \DateTime('2024-06-02')],
            ['id' => 3, 'itemid' => 204, 'paperid' => 4, 'status' => 'pending', 'date' => new \DateTime('2024-07-03 10:00:00'), 'expirationDate' => new \DateTime('2024-08-03')],
            ]);
        });

        // reports: one row by version and reviewer
        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturnCallback(function () use (&$queries, $reports): QueryBuilder {
            $queries['getReceivedReports']++;

            return $this->createQueryBuilder($reports ?? [
            ['docid' => 201, 'paperid' => 1, 'count' => 1, 'date' => '2024-03-20 10:00:00'],
            ['docid' => 203, 'paperid' => 3, 'count' => 1, 'date' => '2024-05-20 10:00:00'],
            ]);
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Paper::class, $papersRepository],
            [PaperLog::class, $paperLogRepository],
            [Review::class, $reviewRepository],
            [PaperConflicts::class, $paperConflictsRepository],
            [UserInvitation::class, $userInvitationRepository],
            [ReviewerReport::class, $reviewerReportRepository],
        ]);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(static fn(string $role): bool => match ($role) {
            'ROLE_EPIADMIN' => $epiAdmin,
            // as in the role hierarchy, ROLE_ADMINISTRATOR covers ROLE_SECRETARY,
            // which is the role actually checked by StatisticPapers::getConflictedPapers
            'ROLE_SECRETARY' => $epiAdmin || ($administrator && $user instanceof User),
            'ROLE_ADMINISTRATOR' => $epiAdmin || ($administrator && $user instanceof User),
            default => false,
        });

        $provider = new StatisticStateProvider($entityManager, $this->createStub(LoggerInterface::class), new Pagination(['client_enabled' => true]), $this->createStub(Stats::class), new StatisticPapers($entityManager, $security));
        $operation = new GetCollection(class: Statistic::class, name: $operationName, paginationMaximumItemsPerPage: 30);
        // the given filters override the defaults; a null filter is removed (e.g. no rvcode)
        $filters = array_filter(array_merge(['pagination' => 'true', 'rvcode' => 'dmtcs', 'withPapers' => 'true', 'unit' => 'day'], $filters), static fn($value): bool => $value !== null);

        return iterator_to_array($provider->provide($operation, [], ['operation' => $operation, 'filters' => $filters, 'request' => $this->request]));
    }

    #[DataProvider('invalidGroupByProvider')]
    public function testInvalidGroupByIsRejected(array $filters): void
    {
        $this->expectException(ValidationException::class);

        $this->provideCollection($filters);
    }

    public static function invalidGroupByProvider(): iterable
    {
        yield 'unknown value' => [['indicator' => 'median-submission-publication', 'groupBy' => 'year']];
        yield 'sent as array' => [['indicator' => 'median-submission-publication', 'groupBy' => ['journal']]];
        yield 'unknown indicator' => [['indicator' => ['nb-submissions', 'foo'], 'groupBy' => 'journal']];
    }

    /**
     * Without indicator, all of them for each active journal, in the order of the indicators ("evaluation" stands for
     * its three indicators); a missing count is 0, a missing rate or median is null.
     */
    public function testAllTheIndicatorsAreGroupedByJournal(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['groupBy' => 'journal', 'unit' => 'day']));

        $rows = array_map(static fn(Statistic $statistic): array => [$statistic->getJournal(), $statistic->getName(), $statistic->getValue(), $statistic->getCount()], $statistics);

        $this->assertSame([
            ['dmtcs', 'nb-submissions', 10.0, null],
            ['dmtcs', 'acceptance-rate', 75.0, null],
            ['dmtcs', 'median-submission-publication', 335.0, 412],
            ['dmtcs', 'median-submission-acceptance', 335.0, 412],
            ['dmtcs', 'reviews-requested', 2.0, null],
            ['dmtcs', 'reviews-received', 3.0, null],
            ['dmtcs', 'median-reviews-number', 2.0, null],
            ['jdmdh', 'nb-submissions', 4.0, null],
            ['jdmdh', 'acceptance-rate', 0.0, null],
            ['jdmdh', 'median-submission-publication', 233.0, 57],
            ['jdmdh', 'median-submission-acceptance', 233.0, 57],
            ['jdmdh', 'reviews-requested', 1.0, null],
            ['jdmdh', 'reviews-received', 0.0, null],
            ['jdmdh', 'median-reviews-number', null, null],
        ], $rows);
    }

    public function testRequestedIndicatorsAreGroupedByJournalInTheirOrder(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['groupBy' => 'journal', 'indicator' => ['acceptance-rate', 'nb-submissions']]));

        $this->assertSame(
            [['dmtcs', 'acceptance-rate'], ['dmtcs', 'nb-submissions'], ['jdmdh', 'acceptance-rate'], ['jdmdh', 'nb-submissions']],
            array_map(static fn(Statistic $statistic): array => [$statistic->getJournal(), $statistic->getName()], $statistics)
        );
        $this->assertSame('%', $statistics[0]->getUnit());
    }

    public function testEvaluationIsGroupedByJournal(): void
    {
        $statistics = iterator_to_array($this->provideCollection(['groupBy' => 'journal', 'indicator' => 'reviews-received'], self::EVALUATION_OPERATION));

        $this->assertSame(
            [['dmtcs', 'reviews-received', 3.0], ['jdmdh', 'reviews-received', 0.0]],
            array_map(static fn(Statistic $statistic): array => [$statistic->getJournal(), $statistic->getName(), $statistic->getValue()], $statistics)
        );
    }

    public function testGroupByIsRejectedWithAPublicationIndicatorOnTheEvaluation(): void
    {
        $this->expectException(ValidationException::class);

        $this->provideCollection(['groupBy' => 'journal', 'indicator' => 'nb-submissions'], self::EVALUATION_OPERATION);
    }

    private function provideCollection(array $filters, string $operationName = self::COLLECTION_OPERATION): iterable
    {
        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);
        $papersRepository->method('countSubmissionsByJournal')->willReturn([1 => 10, 3 => 4]);

        $paperLogRepository = $this->createStub(PaperLogRepository::class);
        $paperLogRepository->method('getAcceptedByJournal')->willReturn([1 => 3]);
        $paperLogRepository->method('getRefusedByJournal')->willReturn([1 => 1, 3 => 2]);
        $paperLogRepository->method('getAcceptanceRate')->willReturnCallback(
            static fn(array $options): float => $options['acceptedTotal'] / ($options['acceptedTotal'] + $options['refusedTotal']) * 100
        );
        $paperLogRepository->method('getSubmissionMedianTimeDetails')->willReturn(['median' => 317.0, 'count' => 1843]);
        $paperLogRepository->method('getSubmissionMedianTimeDetailsByJournal')->willReturn([
            3 => ['median' => 233, 'count' => 57],
            1 => ['median' => 335, 'count' => 412],
            99 => ['median' => 10, 'count' => 1],
        ]);

        $reviewRepository = $this->createStub(ReviewRepository::class);
        $reviewRepository->method('findBy')->willReturn([
            $this->createConfiguredStub(Review::class, ['getRvid' => 3, 'getCode' => 'jdmdh']),
            $this->createConfiguredStub(Review::class, ['getRvid' => 1, 'getCode' => 'dmtcs']),
        ]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        // completed reports by paper (docid) and reviewer; invitations sent to the reviewers
        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturn($this->createQueryBuilder([
            ['rvid' => 1, 'docid' => 10, 'paperid' => 5, 'count' => 2],
            ['rvid' => 1, 'docid' => 10, 'paperid' => 5, 'count' => 1],
            ['rvid' => 1, 'docid' => 11, 'paperid' => 6, 'count' => 1],
        ]));

        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        // one row by invitation
        $userInvitationRepository->method('getReviewsRequested')->willReturn($this->createQueryBuilder([['rvid' => 1, 'id' => 1], ['rvid' => 1, 'id' => 2], ['rvid' => 3, 'id' => 3]]));

        $entityManager->method('getRepository')->willReturnMap([
            [Paper::class, $papersRepository],
            [PaperLog::class, $paperLogRepository],
            [Review::class, $reviewRepository],
            [ReviewerReport::class, $reviewerReportRepository],
            [UserInvitation::class, $userInvitationRepository],
        ]);

        $provider = new StatisticStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true]),
            $this->createStub(Stats::class),
            new StatisticPapers($entityManager, $this->createStub(Security::class))
        );

        $operation = new GetCollection(class: Statistic::class, name: $operationName, paginationMaximumItemsPerPage: 30);

        return $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => 'true'] + $filters, 'request' => $this->request]);
    }

    /**
     * @param array<int, array<string, mixed>> $invitations rows of the requested reviews
     * @param array<string, array<string, mixed>> $repositoryOptions the options given to the repositories, by method
     * @param array<int, array<string, mixed>> $reports rows of the received reports (by paper version and reviewer)
     */
    private function provideEvaluation(string $pagination = 'true', int $maxItemsPerPage = 30, array $filters = [], array $invitations = [], array &$repositoryOptions = [], array $reports = []): iterable
    {
        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);

        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturnCallback(function (array $options) use (&$repositoryOptions, $reports): QueryBuilder {
            $repositoryOptions['getReceivedReports'] = $options;
            return $this->createQueryBuilder($reports);
        });

        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        $userInvitationRepository->method('getReviewsRequested')->willReturnCallback(function (array $options) use (&$repositoryOptions, $invitations): QueryBuilder {
            $repositoryOptions['getReviewsRequested'] = $options;
            return $this->createQueryBuilder($invitations);
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Paper::class, $papersRepository],
            [ReviewerReport::class, $reviewerReportRepository],
            [UserInvitation::class, $userInvitationRepository],
        ]);

        $provider = new StatisticStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true]),
            $this->createStub(Stats::class),
            new StatisticPapers($entityManager, $this->createStub(Security::class))
        );

        $operation = new GetCollection(class: Statistic::class, name: self::EVALUATION_OPERATION, paginationMaximumItemsPerPage: $maxItemsPerPage);

        return $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => $pagination] + $filters, 'request' => $this->request]);
    }

    private function createQueryBuilder(array $rows = [], int $scalar = 0): QueryBuilder
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($rows);
        $query->method('getArrayResult')->willReturn($rows);
        $query->method('getSingleScalarResult')->willReturn($scalar);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('addSelect')->willReturnSelf();

        return $queryBuilder;
    }
}
