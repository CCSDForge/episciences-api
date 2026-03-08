<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\DataProvider\PapersStatsDataProvider;
use App\DataProvider\UsersStatsDataProvider;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\User;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PapersAndUsersStatsDataProviderTest extends TestCase
{
    protected function setUp(): void
    {
    }

    // ── PapersStatsDataProvider::supports ─────────────────────────────────────

    public function testPapersProviderSupportsPaperClassWithValidOperation(): void
    {
        // The 'papers' operations list is currently empty in AppConstants
        // so supports() should always return false for any operation name
        $provider = new PapersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Paper::class);
        $operation->method('getName')->willReturn('any_operation');

        // Empty custom_operations['items']['papers'] array → in_array always false
        $this->assertFalse($provider->supports($operation));
    }

    public function testPapersProviderReturnsFalseForWrongClass(): void
    {
        $provider = new PapersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->assertFalse($provider->supports($operation));
    }

    public function testPapersProviderReturnsFalseForNullOperation(): void
    {
        $provider = new PapersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $this->assertFalse($provider->supports());
    }

    public function testPapersProviderProvidesNullWhenNotSupported(): void
    {
        $provider = new PapersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Paper::class);
        $operation->method('getName')->willReturn('unsupported');

        $this->assertNull($provider->provide($operation));
    }

    // ── UsersStatsDataProvider::supports ──────────────────────────────────────

    public function testUsersProviderSupportsUserClassWithValidOperationName(): void
    {
        $provider = new UsersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $validNames = AppConstants::APP_CONST['custom_operations']['items']['review'];

        foreach ($validNames as $name) {
            $operation = $this->createMock(Operation::class);
            $operation->method('getClass')->willReturn(User::class);
            $operation->method('getName')->willReturn($name);

            $this->assertTrue(
                $provider->supports($operation),
                "Expected supports() to return true for operation '$name' on User"
            );
        }
    }

    public function testUsersProviderReturnsFalseForWrongClass(): void
    {
        $provider = new UsersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class);
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->assertFalse($provider->supports($operation));
    }

    public function testUsersProviderReturnsFalseForNullOperation(): void
    {
        $provider = new UsersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $this->assertFalse($provider->supports());
    }

    public function testUsersProviderProvidesNullWhenNotSupported(): void
    {
        $provider = new UsersStatsDataProvider($this->createStub(\Doctrine\ORM\EntityManagerInterface::class), $this->createStub(\App\Service\Stats::class));

        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Review::class); // wrong class
        $operation->method('getName')->willReturn(AppConstants::STATS_DASHBOARD_ITEM);

        $this->assertNull($provider->provide($operation));
    }
}
