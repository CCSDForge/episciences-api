<?php

namespace App\Tests\Unit\DataProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\DataProvider\AbstractDataProvider;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionOutput;
use App\Resource\UsersStatsOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TestableDataProvider extends AbstractDataProvider
{
    public function supports(Operation $operation = null): bool
    {
        return true;
    }

    public function exposeGetCollection(Operation $operation, array $context = []): mixed
    {
        return $this->getCollection($operation, $context);
    }
}

class AbstractDataProviderTest extends TestCase
{
    private TestableDataProvider $dataProvider;
    private MockObject $entityManager;
    private MockObject $statsService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->statsService = $this->createMock(Stats::class);
        $this->dataProvider = new TestableDataProvider($this->entityManager, $this->statsService);
    }

    public function testThrowsResourceNotFoundExceptionWhenJournalNotFound(): void
    {
        $this->statsService->method('getJournal')->willReturn(null);

        $operation = new Get();
        $context = [
            'uri_variables' => ['code' => 'unknown']
        ];

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Oops! not found Journal unknown');

        $this->dataProvider->exposeGetCollection($operation, $context);
    }

    public function testDashboardItemCallsGetDashboard(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        $dashboardOutput = new DashboardOutput();
        $this->statsService->expects($this->once())
            ->method('getDashboard')
            ->willReturn($dashboardOutput);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][0]);
        
        $context = ['uri_variables' => ['code' => 'myjournal'], 'filters' => []];

        $result = $this->dataProvider->exposeGetCollection($operation, $context);

        $this->assertSame($dashboardOutput, $result);
    }

    public function testNbSubmissionsItemCallsGetSubmissionsStat(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        $submissionOutput = new SubmissionOutput();
        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->willReturn($submissionOutput);

        $operation = new Get(name: AppConstants::APP_CONST['custom_operations']['items']['review'][1]);
        $context = ['uri_variables' => ['code' => 'myjournal'], 'filters' => []];

        $result = $this->dataProvider->exposeGetCollection($operation, $context);

        $this->assertSame($submissionOutput, $result);
    }

    public function testAddFiltersTransformsContextFiltersCorrectly(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->statsService->method('getJournal')->willReturn($journal);

        // We use STATS_NB_SUBMISSIONS_ITEM because it enables additional filters
        $operation = new Get(name: AppConstants::STATS_NB_SUBMISSIONS_ITEM);
        $context = [
            'uri_variables' => ['code' => 'myjournal'],
            'filters' => [
                AppConstants::WITH_DETAILS => 'true',
                AppConstants::START_AFTER_DATE => '2023-01-01',
                AppConstants::YEAR_PARAM => '2022'
            ]
        ];

        $this->statsService->expects($this->once())
            ->method('getSubmissionsStat')
            ->with($this->callback(function (array $filters) {
                // Validates that addFilters mapped parameters correctly
                return isset($filters['is']['rvid']) && $filters['is']['rvid'] === '42'
                    && isset($filters['is'][AppConstants::WITH_DETAILS]) && $filters['is'][AppConstants::WITH_DETAILS] === true
                    && isset($filters['is'][AppConstants::START_AFTER_DATE]) && $filters['is'][AppConstants::START_AFTER_DATE] === '2023-01-01'
                    && isset($filters['is']['submissionDate']) && $filters['is']['submissionDate'] === '2022';
            }))
            ->willReturn(new SubmissionOutput());

        $this->dataProvider->exposeGetCollection($operation, $context);
    }
}
