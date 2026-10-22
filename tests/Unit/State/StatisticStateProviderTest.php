<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\ReviewerReport;
use App\Entity\UserInvitation;
use App\Exception\ResourceNotFoundException;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\ReviewerReportRepository;
use App\Repository\UserInvitationRepository;
use App\Resource\Statistic;
use App\Service\Stats;
use App\State\StatisticStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        $this->expectException(ResourceNotFoundException::class);

        $this->provideCollection(['indicator' => ['median-submission-publication', 'foo']]);
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
        $this->expectException(ResourceNotFoundException::class);

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

    #[DataProvider('invalidGroupByProvider')]
    public function testInvalidGroupByIsRejected(array $filters): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->provideCollection($filters);
    }

    public static function invalidGroupByProvider(): iterable
    {
        yield 'unknown value' => [['indicator' => 'median-submission-publication', 'groupBy' => 'year']];
        yield 'without indicator' => [['groupBy' => 'journal']];
        yield 'not a median' => [['indicator' => 'acceptance-rate', 'groupBy' => 'journal']];
        yield 'sent as array' => [['indicator' => 'median-submission-publication', 'groupBy' => ['journal']]];
        yield 'several indicators' => [['indicator' => ['median-submission-publication', 'median-submission-acceptance'], 'groupBy' => 'journal']];
    }

    public function testGroupByIsRejectedForTheEvaluation(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->provideEvaluation(filters: ['groupBy' => 'journal']);
    }

    private function provideCollection(array $filters): iterable
    {
        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);

        $paperLogRepository = $this->createStub(PaperLogRepository::class);
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
        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturn($this->createQueryBuilder());

        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        $userInvitationRepository->method('getReviewsRequested')->willReturn($this->createQueryBuilder());

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
            $this->createStub(Stats::class)
        );

        $operation = new GetCollection(class: Statistic::class, name: self::COLLECTION_OPERATION, paginationMaximumItemsPerPage: 30);

        return $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => 'true'] + $filters, 'request' => $this->request]);
    }

    private function provideEvaluation(string $pagination = 'true', int $maxItemsPerPage = 30, array $filters = []): iterable
    {
        $papersRepository = $this->createStub(PapersRepository::class);
        $papersRepository->method('getYearRange')->willReturn([]);

        $reviewerReportRepository = $this->createStub(ReviewerReportRepository::class);
        $reviewerReportRepository->method('getReceivedReports')->willReturn($this->createQueryBuilder());

        $userInvitationRepository = $this->createStub(UserInvitationRepository::class);
        $userInvitationRepository->method('getReviewsRequested')->willReturn($this->createQueryBuilder());

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
            $this->createStub(Stats::class)
        );

        $operation = new GetCollection(class: Statistic::class, name: self::EVALUATION_OPERATION, paginationMaximumItemsPerPage: $maxItemsPerPage);

        return $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => $pagination] + $filters, 'request' => $this->request]);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);
        $query->method('getArrayResult')->willReturn([]);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }
}
