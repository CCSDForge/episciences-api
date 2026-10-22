<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\Paper;
use App\Repository\PaperLogRepository;
use PHPUnit\Framework\TestCase;

/**
 * The median delays are returned with the number of articles they are computed from,
 * for all the journals or for each journal (one query).
 */
class PaperLogRepositoryMedianTest extends TestCase
{
    private const array DELAYS = [
        ['rvid' => 1, 'delay' => 10],
        ['rvid' => 2, 'delay' => 4],
        ['rvid' => 1, 'delay' => 30],
        ['rvid' => 1, 'delay' => 20],
        ['rvid' => 2, 'delay' => 8],
    ];

    public function testMedianIsReturnedWithTheNumberOfArticles(): void
    {
        $details = $this->createRepository()->getSubmissionMedianTimeDetails(null, [], null, 'median-submission-publication', 'day');

        $this->assertSame(['median' => 10, 'count' => 5], $details);
    }

    public function testMedianIsComputedForEachJournal(): void
    {
        $details = $this->createRepository()->getSubmissionMedianTimeDetailsByJournal([], null, 'median-submission-publication', 'day');

        $this->assertSame([
            1 => ['median' => 20, 'count' => 3],
            2 => ['median' => 6.0, 'count' => 2],
        ], $details);
    }

    public function testAcceptanceDelaysAreRequestedForTheAcceptanceMedian(): void
    {
        $repository = $this->createRepository(Paper::STATUS_STRICTLY_ACCEPTED);

        $this->assertSame(10, $repository->getSubmissionMedianTimeByStatusQuery(null, [], null, 'median-submission-acceptance', 'day'));
    }

    private function createRepository(int $expectedStatus = Paper::STATUS_PUBLISHED): PaperLogRepository
    {
        $repository = $this->getMockBuilder(PaperLogRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delayBetweenSubmissionAndLatestStatus'])
            ->getMock();

        $repository->expects($this->once())
            ->method('delayBetweenSubmissionAndLatestStatus')
            ->with('DAY', $expectedStatus, null, [], 'median', null)
            ->willReturn(self::DELAYS);

        return $repository;
    }
}
