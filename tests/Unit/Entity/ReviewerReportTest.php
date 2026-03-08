<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ReviewerReport;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReviewerReport entity.
 *
 * Covers:
 * - Constants: TABLE, STATUS_PENDING, STATUS_WIP, STATUS_COMPLETED
 * - All setters/getters (uid, onbehalfUid, docid, status, creationDate, updateDate)
 * - Fluent interface
 * - Null handling for optional fields
 */
final class ReviewerReportTest extends TestCase
{
    private ReviewerReport $report;

    protected function setUp(): void
    {
        $this->report = new ReviewerReport();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('REVIEWER_REPORT', ReviewerReport::TABLE);
    }

    public function testStatusPendingIsZero(): void
    {
        $this->assertSame(0, ReviewerReport::STATUS_PENDING);
    }

    public function testStatusWipIsOne(): void
    {
        $this->assertSame(1, ReviewerReport::STATUS_WIP);
    }

    public function testStatusCompletedIsTwo(): void
    {
        $this->assertSame(2, ReviewerReport::STATUS_COMPLETED);
    }

    public function testStatusConstantsAreDistinct(): void
    {
        $statuses = [ReviewerReport::STATUS_PENDING, ReviewerReport::STATUS_WIP, ReviewerReport::STATUS_COMPLETED];
        $this->assertCount(3, array_unique($statuses));
    }

    public function testStatusConstantsAreInAscendingOrder(): void
    {
        $this->assertLessThan(ReviewerReport::STATUS_WIP, ReviewerReport::STATUS_PENDING);
        $this->assertLessThan(ReviewerReport::STATUS_COMPLETED, ReviewerReport::STATUS_WIP);
    }

    // ── setUid / getUid ───────────────────────────────────────────────────────

    public function testSetUidReturnsSelf(): void
    {
        $result = $this->report->setUid(42);
        $this->assertSame($this->report, $result);
    }

    public function testGetUidReturnsSetValue(): void
    {
        $this->report->setUid(99);
        $this->assertSame(99, $this->report->getUid());
    }

    // ── setOnbehalfUid / getOnbehalfUid ──────────────────────────────────────

    public function testGetOnbehalfUidDefaultsToNull(): void
    {
        $this->assertNull($this->report->getOnbehalfUid());
    }

    public function testSetOnbehalfUidReturnsSelf(): void
    {
        $result = $this->report->setOnbehalfUid(7);
        $this->assertSame($this->report, $result);
    }

    public function testGetOnbehalfUidReturnsSetValue(): void
    {
        $this->report->setOnbehalfUid(55);
        $this->assertSame(55, $this->report->getOnbehalfUid());
    }

    public function testSetOnbehalfUidWithNullResetsToNull(): void
    {
        $this->report->setOnbehalfUid(10);
        $this->report->setOnbehalfUid(null);
        $this->assertNull($this->report->getOnbehalfUid());
    }

    // ── setDocid / getDocid ───────────────────────────────────────────────────

    public function testSetDocidReturnsSelf(): void
    {
        $result = $this->report->setDocid(100);
        $this->assertSame($this->report, $result);
    }

    public function testGetDocidReturnsSetValue(): void
    {
        $this->report->setDocid(200);
        $this->assertSame(200, $this->report->getDocid());
    }

    // ── setStatus / getStatus ─────────────────────────────────────────────────

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->report->setStatus(ReviewerReport::STATUS_PENDING);
        $this->assertSame($this->report, $result);
    }

    public function testGetStatusReturnsSetPending(): void
    {
        $this->report->setStatus(ReviewerReport::STATUS_PENDING);
        $this->assertSame(ReviewerReport::STATUS_PENDING, $this->report->getStatus());
    }

    public function testGetStatusReturnsSetWip(): void
    {
        $this->report->setStatus(ReviewerReport::STATUS_WIP);
        $this->assertSame(ReviewerReport::STATUS_WIP, $this->report->getStatus());
    }

    public function testGetStatusReturnsSetCompleted(): void
    {
        $this->report->setStatus(ReviewerReport::STATUS_COMPLETED);
        $this->assertSame(ReviewerReport::STATUS_COMPLETED, $this->report->getStatus());
    }

    // ── setCreationDate / getCreationDate ─────────────────────────────────────

    public function testSetCreationDateReturnsSelf(): void
    {
        $dt = new DateTime('2023-01-01');
        $result = $this->report->setCreationDate($dt);
        $this->assertSame($this->report, $result);
    }

    public function testGetCreationDateReturnsSetValue(): void
    {
        $dt = new DateTime('2023-06-15');
        $this->report->setCreationDate($dt);
        $this->assertSame($dt, $this->report->getCreationDate());
    }

    // ── setUpdateDate / getUpdateDate ─────────────────────────────────────────

    public function testGetUpdateDateDefaultsToNull(): void
    {
        $this->assertNull($this->report->getUpdateDate());
    }

    public function testSetUpdateDateReturnsSelf(): void
    {
        $dt = new DateTime('2023-07-01');
        $result = $this->report->setUpdateDate($dt);
        $this->assertSame($this->report, $result);
    }

    public function testGetUpdateDateReturnsSetValue(): void
    {
        $dt = new DateTime('2024-01-20');
        $this->report->setUpdateDate($dt);
        $this->assertSame($dt, $this->report->getUpdateDate());
    }

    public function testSetUpdateDateWithNullResetsToNull(): void
    {
        $this->report->setUpdateDate(new DateTime());
        $this->report->setUpdateDate(null);
        $this->assertNull($this->report->getUpdateDate());
    }
}
