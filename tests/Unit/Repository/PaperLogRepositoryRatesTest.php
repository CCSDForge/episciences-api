<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\PaperLogRepository;
use PHPUnit\Framework\TestCase;

/**
 * The rates are computed from the totals counted by submission by the callers (see SubmissionFigures):
 * without them, no rate is computed.
 */
class PaperLogRepositoryRatesTest extends TestCase
{
    public function testRatesAreComputedFromTheGivenTotals(): void
    {
        $repository = $this->createRepository();
        $options = ['acceptedTotal' => 3, 'refusedTotal' => 1, 'publishedTotal' => 2];

        $this->assertSame(75.0, $repository->getAcceptanceRate($options));
        $this->assertSame(25.0, $repository->getRefusalRate($options));
        $this->assertSame(50.0, $repository->getPublicationRate($options));
        $this->assertNull($repository->getAcceptanceRate(['acceptedTotal' => 0, 'refusedTotal' => 0]));
    }

    public function testTheAcceptedAndRefusedTotalsAreRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createRepository()->getAcceptanceRate(['acceptedTotal' => 3]);
    }

    public function testThePublishedTotalIsRequiredForThePublicationRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createRepository()->getPublicationRate(['acceptedTotal' => 3, 'refusedTotal' => 1]);
    }

    private function createRepository(): PaperLogRepository
    {
        return $this->getMockBuilder(PaperLogRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}
