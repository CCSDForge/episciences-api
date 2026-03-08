<?php

declare(strict_types=1);

namespace App\Tests\Unit\Traits;

use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CheckExistingResourceTrait::checkFilters().
 *
 * checkFilters($available, $requested) delegates to
 * checkArrayEquality($available, array_keys($requested)).
 *
 * checkArrayEquality($tab1, $tab2) semantics:
 *   - equality=true when tab1 === tab2 (same set)
 *   - arrayDiff['in']  = items in $available NOT present among requested keys
 *   - arrayDiff['out'] = requested keys NOT present in $available
 */
final class CheckExistingResourceTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $this->subject = new class ($em) {
            use CheckExistingResourceTrait;
        };
    }

    // ── Exact match → equality=true, no arrayDiff sub-keys ───────────────────

    public function testCheckFiltersExactMatchSetsEquality(): void
    {
        $result = $this->subject->checkFilters(
            ['rvid', 'status'],
            ['rvid' => 42, 'status' => 'published']
        );

        $this->assertTrue($result['equality']);
    }

    // ── No requested filters → 'in' contains all available ───────────────────

    public function testCheckFiltersWithNoRequestedAllAvailableAppearsInIn(): void
    {
        $result = $this->subject->checkFilters(['rvid', 'status']);

        // All available are "not requested", so they go into 'in'
        $this->assertContains('rvid', $result['arrayDiff']['in']);
        $this->assertContains('status', $result['arrayDiff']['in']);
        $this->assertSame([], $result['arrayDiff']['out']);
    }

    // ── Unknown requested filter → appears in 'out' ───────────────────────────

    public function testCheckFiltersUnknownRequestedAppearsInOut(): void
    {
        $result = $this->subject->checkFilters(
            ['rvid', 'status'],
            ['rvid' => 42, 'unknown_filter' => 'value']
        );

        // 'unknown_filter' is requested but not in available
        $this->assertContains('unknown_filter', $result['arrayDiff']['out']);
    }

    // ── Partial match ─────────────────────────────────────────────────────────

    public function testCheckFiltersAvailableNotRequestedAppearsInIn(): void
    {
        // 'year' is available but not requested → goes to 'in'
        $result = $this->subject->checkFilters(
            ['rvid', 'status', 'year'],
            ['rvid' => 42, 'status' => 'accepted']
        );

        $this->assertContains('year', $result['arrayDiff']['in']);
        $this->assertSame([], $result['arrayDiff']['out']);
    }

    // ── Both empty → equality=true ────────────────────────────────────────────

    public function testCheckFiltersWithBothEmptySetsEquality(): void
    {
        $result = $this->subject->checkFilters([], []);
        $this->assertTrue($result['equality']);
    }

    // ── No available → all requested go to 'out' ──────────────────────────────

    public function testCheckFiltersWithNoAvailableAllRequestedGoToOut(): void
    {
        $result = $this->subject->checkFilters(
            [],
            ['rvid' => 42, 'status' => 'published']
        );

        $this->assertContains('rvid', $result['arrayDiff']['out']);
        $this->assertContains('status', $result['arrayDiff']['out']);
    }
}
