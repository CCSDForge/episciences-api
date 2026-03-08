<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\DataProvider\ReviewStatsDataProvider;
use App\Entity\Review;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionOutput;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReviewStatsDataProviderTest extends TestCase
{
    private Stats $stats;

    protected function setUp(): void
    {
        $this->stats = $this->createMock(Stats::class);
    }

    private function makeProvider(): ReviewStatsDataProvider
    {
        return new ReviewStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->stats);
    }

    // ── supports ──────────────────────────────────────────────────────────────

    public function testSupportsReturnsTrueForReviewClassAndValidOperationName(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->assertTrue($this->makeProvider()->supports($operation));
    }

    public function testSupportsReturnsTrueForAllValidOperationNames(): void
    {
        $validNames = AppConstants::APP_CONST['custom_operations']['items']['review'];

        foreach ($validNames as $name) {
            $operation = $this->createMock(Operation::class);
            $operation->method('getClass')->willReturn(Review::class);
            $operation->method('getName')->willReturn($name);

            $this->assertTrue(
                $this->makeProvider()->supports($operation),
                "Expected supports() to return true for operation '$name'"
            );
        }
    }

    public function testSupportsReturnsFalseForWrongClass(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(User::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->assertFalse($this->makeProvider()->supports($operation));
    }

    public function testSupportsReturnsFalseForUnknownOperationName(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn('unknown_operation');

        $this->assertFalse($this->makeProvider()->supports($operation));
    }

    public function testSupportsReturnsFalseForNullOperation(): void
    {
        $this->assertFalse($this->makeProvider()->supports());
    }

    // ── provide: unsupported operation → null ─────────────────────────────────

    public function testProvideReturnsNullWhenNotSupported(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(User::class); // wrong class
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $result = $this->makeProvider()->provide($operation);

        $this->assertNull($result);
    }

    // ── provide: journal not found → ResourceNotFoundException ───────────────

    public function testProvideThrowsWhenJournalNotFound(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found Journal/i');

        $this->stats->method('getJournal')->willReturn(null);

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'unknowncode'],
            'filters'       => [],
        ]);
    }

    // ── provide: dashboard operation → getDashboard called ───────────────────

    public function testProvideDashboardCallsGetDashboard(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $this->stats->method('getJournal')->willReturn($journal);

        $dashboardOutput = new DashboardOutput();
        $this->stats->expects($this->once())
            ->method('getDashboard')
            ->willReturn($dashboardOutput);

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $result = $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [],
        ]);

        $this->assertSame($dashboardOutput, $result);
    }

    // ── provide: submissions operation → getSubmissionsStat called ───────────

    public function testProvideSubmissionsCallsGetSubmissionsStat(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $this->stats->method('getJournal')->willReturn($journal);

        $submissionOutput = new SubmissionOutput();
        $this->stats->expects($this->once())
            ->method('getSubmissionsStat')
            ->willReturn($submissionOutput);

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_NB_SUBMISSIONS_ITEM);

        $result = $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [],
        ]);

        $this->assertSame($submissionOutput, $result);
    }

    // ── addFilters: withDetails filter propagated ─────────────────────────────

    public function testAddFiltersWithDetailsIsPropagatedToSubmissionStats(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(5);

        $this->stats->method('getJournal')->willReturn($journal);
        $this->stats->method('getSubmissionsStat')->willReturn(new SubmissionOutput());

        $capturedFilters = null;
        $this->stats->method('getSubmissionsStat')
            ->willReturnCallback(function (array $filters) use (&$capturedFilters): SubmissionOutput {
                $capturedFilters = $filters;
                return new SubmissionOutput();
            });

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_NB_SUBMISSIONS_ITEM);

        $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [AppConstants::WITH_DETAILS => '1'],
        ]);

        $this->assertArrayHasKey(AppConstants::WITH_DETAILS, $capturedFilters['is']);
        $this->assertTrue($capturedFilters['is'][AppConstants::WITH_DETAILS]);
    }

    // ── addFilters: startAfterDate with valid date propagated ─────────────────

    public function testAddFiltersValidStartAfterDateIsPropagated(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(5);

        $this->stats->method('getJournal')->willReturn($journal);

        $capturedFilters = null;
        $this->stats->method('getSubmissionsStat')
            ->willReturnCallback(function (array $filters) use (&$capturedFilters): SubmissionOutput {
                $capturedFilters = $filters;
                return new SubmissionOutput();
            });

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_NB_SUBMISSIONS_ITEM);

        $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [AppConstants::START_AFTER_DATE => '2023-01-01'],
        ]);

        $this->assertArrayHasKey(AppConstants::START_AFTER_DATE, $capturedFilters['is']);
        $this->assertSame('2023-01-01', $capturedFilters['is'][AppConstants::START_AFTER_DATE]);
    }

    // ── addFilters: startAfterDate with invalid date NOT propagated ───────────

    public function testAddFiltersInvalidStartAfterDateIsIgnored(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(5);

        $this->stats->method('getJournal')->willReturn($journal);

        $capturedFilters = null;
        $this->stats->method('getSubmissionsStat')
            ->willReturnCallback(function (array $filters) use (&$capturedFilters): SubmissionOutput {
                $capturedFilters = $filters;
                return new SubmissionOutput();
            });

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_NB_SUBMISSIONS_ITEM);

        $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [AppConstants::START_AFTER_DATE => 'not-a-date'],
        ]);

        $this->assertArrayNotHasKey(AppConstants::START_AFTER_DATE, $capturedFilters['is']);
    }

    // ── addFilters: year param maps to submissionDate ─────────────────────────

    public function testAddFiltersYearParamMapsToSubmissionDate(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(5);

        $this->stats->method('getJournal')->willReturn($journal);

        $capturedFilters = null;
        $this->stats->method('getSubmissionsStat')
            ->willReturnCallback(function (array $filters) use (&$capturedFilters): SubmissionOutput {
                $capturedFilters = $filters;
                return new SubmissionOutput();
            });

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_NB_SUBMISSIONS_ITEM);

        $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [AppConstants::YEAR_PARAM => '2022'],
        ]);

        $this->assertArrayHasKey('submissionDate', $capturedFilters['is']);
        $this->assertSame('2022', $capturedFilters['is']['submissionDate']);
    }

    // ── addFilters: no uri_variables → no stats call ──────────────────────────

    public function testGetCollectionWithoutUriVariablesReturnsNull(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->stats->expects($this->never())->method('getDashboard');

        $result = $this->makeProvider()->provide($operation, [], [
            'filters' => [],
            // no 'uri_variables' key
        ]);

        $this->assertNull($result);
    }

    // ── provide: delay acceptance → getDelayBetweenSubmissionAndLatestStatus ──

    public function testProvideDelayAcceptanceCallsDelayService(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(3);

        $this->stats->method('getJournal')->willReturn($journal);

        $output = new \App\Resource\SubmissionAcceptanceDelayOutput();
        $this->stats->expects($this->once())
            ->method('getDelayBetweenSubmissionAndLatestStatus')
            ->willReturn($output);

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DELAY_SUBMISSION_ACCEPTANCE);

        $result = $this->makeProvider()->provide($operation, [], [
            'uri_variables' => ['code' => 'myjournal'],
            'filters'       => [],
        ]);

        $this->assertSame($output, $result);
    }
}
