<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use App\AppConstants;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use App\State\AbstractStateDataProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Concrete subclass to expose protected methods under test.
 */
final class ConcreteStateDataProvider extends AbstractStateDataProvider
{
    // expose for testing
    public function callCheckAndProcessFilters(array &$context): void
    {
        $this->checkAndProcessFilters($context);
    }

    public function callCheckSeekPosition(\ApiPlatform\State\Pagination\PaginatorInterface $paginator): void
    {
        $this->checkSeekPosition($paginator);
    }
}

final class AbstractStateDataProviderTest extends TestCase
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private ConcreteStateDataProvider $provider;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $pagination = new Pagination();
        $this->provider = new ConcreteStateDataProvider($this->em, $this->logger, $pagination);
    }

    // ── checkAndProcessFilters: page parsing ──────────────────────────────────

    public function testPageDefaultsToOneWhenNotSet(): void
    {
        $context = ['filters' => []];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertSame(1, $context['filters']['page']);
    }

    public function testPageParsedCorrectly(): void
    {
        $context = ['filters' => ['page' => '3']];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertSame(3, $context['filters']['page']);
    }

    public function testPageZeroFallsBackToOne(): void
    {
        $context = ['filters' => ['page' => '0']];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertSame(1, $context['filters']['page']);
    }

    // ── checkAndProcessFilters: pagination parsing ────────────────────────────

    public function testPaginationDefaultsToTrueWhenNotSet(): void
    {
        $context = ['filters' => []];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertTrue($context['filters']['pagination']);
    }

    public function testPaginationFalseStringParsedCorrectly(): void
    {
        $context = ['filters' => ['pagination' => 'false']];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertFalse($context['filters']['pagination']);
    }

    public function testPaginationTrueStringParsedCorrectly(): void
    {
        $context = ['filters' => ['pagination' => 'true']];
        $this->provider->callCheckAndProcessFilters($context);
        $this->assertTrue($context['filters']['pagination']);
    }

    // ── checkAndProcessFilters: code resolution ───────────────────────────────

    public function testCodePlaceholderSkipsJournalLookup(): void
    {
        $this->em->expects($this->never())->method('getRepository');

        $context = ['filters' => ['code' => '{code}']];
        $this->provider->callCheckAndProcessFilters($context);

        $this->assertNull($context[AbstractStateDataProvider::CONTEXT_JOURNAL_KEY]);
    }

    public function testUnknownCodeThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/unknown/');

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn(null);

        $this->em->method('getRepository')
            ->with(Review::class)
            ->willReturn($reviewRepo);

        $context = ['filters' => ['code' => 'unknown']];
        $this->provider->callCheckAndProcessFilters($context);
    }

    public function testValidCodeSetsRvIdInFilters(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);
        $journal->method('getSetting')->willReturn(null);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $this->em->method('getRepository')
            ->with(Review::class)
            ->willReturn($reviewRepo);

        $context = ['filters' => ['code' => 'myjournal']];
        $this->provider->callCheckAndProcessFilters($context);

        $this->assertSame(42, $context['filters']['rvid']);
        $this->assertSame($journal, $context[AbstractStateDataProvider::CONTEXT_JOURNAL_KEY]);
    }

    public function testRvcodeIsAlsoAcceptedAsJournalCode(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);
        $journal->method('getSetting')->willReturn(null);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $this->em->method('getRepository')
            ->with(Review::class)
            ->willReturn($reviewRepo);

        $context = ['filters' => ['rvcode' => 'myjournal']];
        $this->provider->callCheckAndProcessFilters($context);

        $this->assertSame(7, $context['filters']['rvid']);
    }

    // ── checkAndProcessFilters: year processing ───────────────────────────────

    public function testYearFiltersAreProcessed(): void
    {
        $context = ['filters' => [AppConstants::YEAR_PARAM => ['2021', '2022', '2021']]];
        $this->provider->callCheckAndProcessFilters($context);

        // processYears deduplicates
        $years = $context['filters'][AppConstants::YEAR_PARAM];
        $this->assertCount(2, $years);
        $this->assertContains('2021', $years);
        $this->assertContains('2022', $years);
    }

    // ── checkSeekPosition ────────────────────────────────────────────────────

    public function testValidPageDoesNotThrow(): void
    {
        // 3 items total, 10 per page → 1 page
        $paginator = new ArrayPaginator(['a', 'b', 'c'], 0, 10);
        // no exception
        $this->provider->callCheckSeekPosition($paginator);
        $this->addToAssertionCount(1);
    }

    public function testOutOfRangePageThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/out of range/i');

        // 3 items, 1 per page → last page = 3, but requesting page 5 via firstResult
        $paginator = new ArrayPaginator(['a', 'b', 'c'], 40, 1); // firstResult=40 → page=41
        $this->provider->callCheckSeekPosition($paginator);
    }
}
