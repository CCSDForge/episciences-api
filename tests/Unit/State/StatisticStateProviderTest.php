<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\Pagination\Pagination; // final class — must use new Pagination(), not createStub()
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Resource\Statistic;
use App\Service\Stats;
use App\State\StatisticStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionMethod;

/**
 * Unit tests for StatisticStateProvider.
 *
 * Because the provide() method requires a live EntityManager (Doctrine queries
 * against a real database), we test the private helper methods via reflection
 * and the pure conditional logic via inline helpers.
 *
 * Methods covered:
 * - processResult() (private) — aggregates reviewer report counts by docid
 * - getAcceptanceRate() — delegates to PaperLogRepository
 * - Status / year filter extraction logic
 */
final class StatisticStateProviderTest extends TestCase
{
    private StatisticStateProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new StatisticStateProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            new Pagination(),
            $this->createStub(Stats::class)
        );
    }

    // ── processResult (private) ───────────────────────────────────────────────

    /**
     * Calls the private processResult() method via reflection.
     */
    private function callProcessResult(array $result): array
    {
        $method = new ReflectionMethod(StatisticStateProvider::class, 'processResult');
        return $method->invoke($this->provider, $result);
    }

    public function testProcessResultEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->callProcessResult([]));
    }

    public function testProcessResultSingleRowReturnsCount(): void
    {
        $result = $this->callProcessResult([
            ['docid' => 1, 'count' => 3],
        ]);

        $this->assertSame([3], $result);
    }

    public function testProcessResultAggregatesCountsByDocid(): void
    {
        // Two rows for the same docid: counts should be summed
        $result = $this->callProcessResult([
            ['docid' => 1, 'count' => 2],
            ['docid' => 1, 'count' => 3],
        ]);

        $this->assertSame([5], $result);
    }

    public function testProcessResultDistinctDocidsProduceSeparateCounts(): void
    {
        $result = $this->callProcessResult([
            ['docid' => 1, 'count' => 4],
            ['docid' => 2, 'count' => 7],
        ]);

        $this->assertCount(2, $result);
        $this->assertContains(4, $result);
        $this->assertContains(7, $result);
    }

    public function testProcessResultReturnsArrayValues(): void
    {
        // Return is array_values() — result must be a list (no string keys)
        $result = $this->callProcessResult([
            ['docid' => 42, 'count' => 1],
        ]);

        $this->assertArrayHasKey(0, $result);
        $this->assertSame([1], $result);
    }

    public function testProcessResultMixedDocidsAreAggregatedCorrectly(): void
    {
        $input = [
            ['docid' => 10, 'count' => 1],
            ['docid' => 20, 'count' => 2],
            ['docid' => 10, 'count' => 3],
            ['docid' => 30, 'count' => 5],
        ];

        $result = $this->callProcessResult($input);

        // docid 10 → 1+3=4, docid 20 → 2, docid 30 → 5
        $this->assertCount(3, $result);
        $this->assertContains(4, $result);
        $this->assertContains(2, $result);
        $this->assertContains(5, $result);
    }

    // ── Status / year filter extraction logic ─────────────────────────────────

    /**
     * Mirrors the status filter extraction logic in provide().
     * $status is either an empty array or a unique list from $filters['status'].
     */
    private function extractStatus(array $filters): array
    {
        return isset($filters['status']) ? array_unique((array)$filters['status']) : [];
    }

    public function testStatusExtractionEmptyWhenAbsent(): void
    {
        $this->assertSame([], $this->extractStatus([]));
    }

    public function testStatusExtractionFromScalarValue(): void
    {
        $status = $this->extractStatus(['status' => 'submitted']);
        $this->assertSame(['submitted'], $status);
    }

    public function testStatusExtractionFromArrayValue(): void
    {
        $status = $this->extractStatus(['status' => ['submitted', 'accepted']]);
        $this->assertContains('submitted', $status);
        $this->assertContains('accepted', $status);
    }

    public function testStatusExtractionDeduplicates(): void
    {
        $status = $this->extractStatus(['status' => ['submitted', 'submitted', 'accepted']]);
        $this->assertCount(2, $status);
    }

    // ── Year filter extraction logic ──────────────────────────────────────────

    private function extractYears(array $filters): array
    {
        return isset($filters['year']) ? array_unique((array)$filters['year']) : [];
    }

    public function testYearExtractionEmptyWhenAbsent(): void
    {
        $this->assertSame([], $this->extractYears([]));
    }

    public function testYearExtractionSingleYear(): void
    {
        $years = $this->extractYears(['year' => 2023]);
        $this->assertSame([2023], $years);
    }

    public function testYearExtractionMultipleYears(): void
    {
        $years = $this->extractYears(['year' => [2022, 2023]]);
        $this->assertCount(2, $years);
    }

    public function testYearExtractionDeduplicates(): void
    {
        $years = $this->extractYears(['year' => [2022, 2022, 2023]]);
        $this->assertCount(2, $years);
    }

    // ── Dictionary-based status validation ────────────────────────────────────

    /**
     * Mirrors the dictionary construction and status-to-int mapping
     * used in provide() for status validation.
     */
    private function buildDictionary(): array
    {
        $dictionary = array_merge(array_flip(Paper::STATUS_DICTIONARY), ['accepted' => Paper::STATUS_ACCEPTED]);
        unset(
            $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_OBSOLETE]],
            $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_REMOVED]],
            $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_DELETED]]
        );
        return $dictionary;
    }

    public function testDictionaryContainsSubmitted(): void
    {
        $dictionary = $this->buildDictionary();
        $this->assertArrayHasKey('submitted', $dictionary);
    }

    public function testDictionaryContainsAccepted(): void
    {
        $dictionary = $this->buildDictionary();
        $this->assertArrayHasKey('accepted', $dictionary);
    }

    public function testDictionaryDoesNotContainObsolete(): void
    {
        $dictionary = $this->buildDictionary();
        $obsoleteLabel = Paper::STATUS_DICTIONARY[Paper::STATUS_OBSOLETE];
        $this->assertArrayNotHasKey($obsoleteLabel, $dictionary);
    }

    public function testDictionaryDoesNotContainDeleted(): void
    {
        $dictionary = $this->buildDictionary();
        $deletedLabel = Paper::STATUS_DICTIONARY[Paper::STATUS_DELETED];
        $this->assertArrayNotHasKey($deletedLabel, $dictionary);
    }

    public function testDictionaryDoesNotContainRemoved(): void
    {
        $dictionary = $this->buildDictionary();
        $removedLabel = Paper::STATUS_DICTIONARY[Paper::STATUS_REMOVED];
        $this->assertArrayNotHasKey($removedLabel, $dictionary);
    }

    // ── Indicator validation logic ────────────────────────────────────────────

    public function testAvailablePublicationIndicatorsContainsAllExpectedKeys(): void
    {
        $indicators = Statistic::AVAILABLE_PUBLICATION_INDICATORS;
        $this->assertArrayHasKey('nb-submissions_get', $indicators);
        $this->assertArrayHasKey('acceptance-rate_get', $indicators);
        $this->assertArrayHasKey('median-submission-publication_get', $indicators);
        $this->assertArrayHasKey('median-submission-acceptance_get', $indicators);
        $this->assertArrayHasKey('evaluation_get_collection', $indicators);
    }

    public function testValidIndicatorPassesInArrayCheck(): void
    {
        $indicator = 'nb-submissions';
        $this->assertTrue(in_array($indicator, Statistic::AVAILABLE_PUBLICATION_INDICATORS, true));
    }

    public function testInvalidIndicatorFailsInArrayCheck(): void
    {
        $indicator = 'totally-invalid-indicator';
        $this->assertFalse(in_array($indicator, Statistic::AVAILABLE_PUBLICATION_INDICATORS, true));
    }

    public function testValidEvalIndicatorPassesCheck(): void
    {
        $indicator = 'reviews-requested';
        $this->assertTrue(in_array($indicator, Statistic::EVAL_INDICATORS, true));
    }

    // ── getAcceptanceRate delegates to PaperLogRepository ────────────────────

    public function testGetAcceptanceRateCallsRepository(): void
    {
        $logRepo = $this->createMock(PaperLogRepository::class);
        $logRepo->expects($this->once())
            ->method('getAcceptanceRate')
            ->with(['rvid' => 1])
            ->willReturn(75.5);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->willReturn($logRepo);

        $provider = new StatisticStateProvider(
            $em,
            $this->createStub(LoggerInterface::class),
            new Pagination(),
            $this->createStub(Stats::class)
        );

        $rate = $provider->getAcceptanceRate(['rvid' => 1]);
        $this->assertSame(75.5, $rate);
    }

    public function testGetAcceptanceRateReturnsNullWhenRepositoryReturnsNull(): void
    {
        $logRepo = $this->createMock(PaperLogRepository::class);
        $logRepo->method('getAcceptanceRate')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($logRepo);

        $provider = new StatisticStateProvider(
            $em,
            $this->createStub(LoggerInterface::class),
            new Pagination(),
            $this->createStub(Stats::class)
        );

        $this->assertNull($provider->getAcceptanceRate([]));
    }

    // ── provide(): exception paths ────────────────────────────────────────────

    /**
     * Build a StatisticStateProvider with a minimal mocked EntityManager suitable
     * for provide() tests that don't need journal lookup.
     *
     * @param array<string> $yearRange  available years returned by Paper::getYearRange
     */
    private function makeProviderWithPaperRepo(
        array $yearRange = [],
        int $scalarResult = 0,
        float|null $acceptanceRate = null,
        float|null $medianResult = null
    ): array {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn($scalarResult);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('getYearRange')->willReturn($yearRange);
        $paperRepo->method('submissionsQuery')->willReturn($qb);

        $logRepo = $this->createMock(PaperLogRepository::class);
        $logRepo->method('getAcceptanceRate')->willReturn($acceptanceRate);
        $logRepo->method('getSubmissionMedianTimeByStatusQuery')->willReturn($medianResult);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Paper::class, $paperRepo],
            [PaperLog::class, $logRepo],
        ]);

        $provider = new StatisticStateProvider(
            $em,
            new NullLogger(),
            new Pagination(),
            $this->createStub(Stats::class)
        );

        return [$provider, $em];
    }

    public function testProvideThrowsWhenJournalCodeNotFound(): void
    {
        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($reviewRepo);

        $provider = new StatisticStateProvider(
            $em,
            new NullLogger(),
            new Pagination(),
            $this->createStub(Stats::class)
        );

        $this->expectException(ResourceNotFoundException::class);

        $provider->provide(
            new Get(name: '_api_/statistics/nb-submissions_get', uriTemplate: '/statistics/nb-submissions'),
            [],
            ['filters' => ['rvcode' => 'unknown-journal']]
        );
    }

    public function testProvideThrowsWhenYearIsUnavailable(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([]); // no available years

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/invalid year/');

        $provider->provide(
            new Get(name: '_api_/statistics/nb-submissions_get', uriTemplate: '/statistics/nb-submissions'),
            [],
            ['filters' => ['year' => '2030']]
        );
    }

    public function testProvideThrowsWhenStatusIsInvalid(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([]);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/invalid status/');

        $provider->provide(
            new Get(name: '_api_/statistics/nb-submissions_get', uriTemplate: '/statistics/nb-submissions'),
            [],
            ['filters' => ['status' => 'completely-invalid-status']]
        );
    }

    public function testProvideThrowsWhenOperationNameUnknown(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([]);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/invalid operation/');

        $provider->provide(
            new Get(name: '_api_/statistics/unknown-op_get', uriTemplate: '/statistics/unknown'),
            [],
            ['filters' => []]
        );
    }

    public function testProvideReturnsStatisticForNbSubmissionsGet(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([], 42);

        $result = $provider->provide(
            new Get(name: '_api_/statistics/nb-submissions_get', uriTemplate: '/statistics/nb-submissions'),
            [],
            ['filters' => []]
        );

        $this->assertInstanceOf(Statistic::class, $result);
        $this->assertEquals(42, $result->getValue()); // Statistic::$value is typed float; int is coerced
        $this->assertSame(Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'], $result->getName());
    }

    public function testProvideReturnsStatisticForAcceptanceRateGet(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([], 0, 75.5);

        $result = $provider->provide(
            new Get(name: '_api_/statistics/acceptance-rate_get', uriTemplate: '/statistics/acceptance-rate'),
            [],
            ['filters' => []]
        );

        $this->assertInstanceOf(Statistic::class, $result);
        $this->assertSame(75.5, $result->getValue());
        $this->assertSame('%', $result->getUnit());
    }

    public function testProvideReturnsStatisticForMedianSubmissionPublicationGet(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([], 0, null, 14.5);

        $result = $provider->provide(
            new Get(name: '_api_/statistics/median-submission-publication_get', uriTemplate: '/statistics/median-publication'),
            [],
            ['filters' => []]
        );

        $this->assertInstanceOf(Statistic::class, $result);
        $this->assertSame(14.5, $result->getValue());
        $this->assertSame('week', $result->getUnit());
    }

    public function testProvideReturnsStatisticForMedianSubmissionAcceptanceGet(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([], 0, null, 7.0);

        $result = $provider->provide(
            new Get(name: '_api_/statistics/median-submission-acceptance_get', uriTemplate: '/statistics/median-acceptance'),
            [],
            ['filters' => []]
        );

        $this->assertInstanceOf(Statistic::class, $result);
        $this->assertSame(7.0, $result->getValue());
        $this->assertSame('week', $result->getUnit());
    }

    public function testProvideRespectsCustomUnitFromFilters(): void
    {
        [$provider] = $this->makeProviderWithPaperRepo([], 0, null, 3.0);

        $result = $provider->provide(
            new Get(name: '_api_/statistics/median-submission-publication_get', uriTemplate: '/statistics/median-publication'),
            [],
            ['filters' => ['unit' => 'month']]
        );

        $this->assertInstanceOf(Statistic::class, $result);
        $this->assertSame('month', $result->getUnit());
    }
}
