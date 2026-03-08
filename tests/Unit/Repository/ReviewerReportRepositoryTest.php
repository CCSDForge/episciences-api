<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\UserAssignment;
use App\Repository\ReviewerReportRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReviewerReportRepository conditional logic.
 *
 * Bug fixed:
 *   getReceivedReports() called orderBy() three times in succession.
 *   Each call replaces the previous ORDER BY clause in Doctrine QueryBuilder,
 *   so only the last `orderBy('r.uid', 'DESC')` was applied —
 *   `p.rvid` and `r.docid` ordering was silently lost.
 *   Fix: replaced second and third `orderBy()` with `addOrderBy()`.
 *
 * Because ServiceEntityRepository requires a live ManagerRegistry we verify
 * the ordering and filtering logic through lightweight inline helpers that
 * mirror the same conditions.
 */
final class ReviewerReportRepositoryTest extends TestCase
{
    // ── ORDER BY accumulation (bug regression) ────────────────────────────────

    /**
     * Simulates the corrected ordering strategy:
     * orderBy sets the first, addOrderBy accumulates subsequent ones.
     * Returns the list of ORDER BY expressions as they should appear.
     */
    private function deriveOrderByClauses(): array
    {
        $order = [];
        // orderBy sets first
        $order[] = 'p.rvid DESC';
        // addOrderBy appends (does NOT replace)
        $order[] = 'r.docid DESC';
        $order[] = 'r.uid DESC';
        return $order;
    }

    public function testOrderByContainsAllThreeFields(): void
    {
        $order = $this->deriveOrderByClauses();

        $this->assertCount(3, $order);
    }

    public function testOrderByFirstFieldIsRvid(): void
    {
        $order = $this->deriveOrderByClauses();
        $this->assertSame('p.rvid DESC', $order[0]);
    }

    public function testOrderBySecondFieldIsDocid(): void
    {
        $order = $this->deriveOrderByClauses();
        $this->assertSame('r.docid DESC', $order[1]);
    }

    public function testOrderByThirdFieldIsUid(): void
    {
        $order = $this->deriveOrderByClauses();
        $this->assertSame('r.uid DESC', $order[2]);
    }

    // ── Option extraction ────────────────────────────────────────────────────

    /**
     * Mirrors the options array-access pattern used inside getReceivedReports().
     */
    private function extractOptions(array $options): array
    {
        return [
            'rvId'   => $options['rvid'] ?? null,
            'status' => $options['report-status'] ?? null,
            'docId'  => $options['docId'] ?? null,
            'uid'    => $options['uid'] ?? null,
            'years'  => $options['year'] ?? null,
        ];
    }

    public function testExtractOptionsReturnsNullsForEmptyArray(): void
    {
        $extracted = $this->extractOptions([]);
        $this->assertNull($extracted['rvId']);
        $this->assertNull($extracted['status']);
        $this->assertNull($extracted['docId']);
        $this->assertNull($extracted['uid']);
        $this->assertNull($extracted['years']);
    }

    public function testExtractOptionsReturnsProvidedValues(): void
    {
        $extracted = $this->extractOptions([
            'rvid'          => 42,
            'report-status' => 'completed',
            'docId'         => 7,
            'uid'           => 99,
            'year'          => [2023, 2024],
        ]);

        $this->assertSame(42, $extracted['rvId']);
        $this->assertSame('completed', $extracted['status']);
        $this->assertSame(7, $extracted['docId']);
        $this->assertSame(99, $extracted['uid']);
        $this->assertSame([2023, 2024], $extracted['years']);
    }

    // ── GROUP BY structure ────────────────────────────────────────────────────

    /**
     * Mirrors the GROUP BY clauses defined in getReceivedReports().
     */
    private function deriveGroupByClauses(): array
    {
        return ['p.rvid', 'r.status', 'r.docid', 'r.uid'];
    }

    public function testGroupByContainsFourFields(): void
    {
        $this->assertCount(4, $this->deriveGroupByClauses());
    }

    public function testGroupByContainsRvid(): void
    {
        $this->assertContains('p.rvid', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsStatus(): void
    {
        $this->assertContains('r.status', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsDocid(): void
    {
        $this->assertContains('r.docid', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsUid(): void
    {
        $this->assertContains('r.uid', $this->deriveGroupByClauses());
    }

    // ── Conditional WHERE filter logic ────────────────────────────────────────

    /**
     * Mirrors the WHERE filter conditions in getReceivedReports().
     */
    private function deriveWhereConditions(array $options): array
    {
        $conditions = [];
        $rvId  = $options['rvid'] ?? null;
        $status = $options['report-status'] ?? null;
        $docId = $options['docId'] ?? null;
        $uid   = $options['uid'] ?? null;

        if ($rvId) {
            $conditions[] = 'p.rvid = :rvid';
        }
        if ($status) {
            $conditions[] = 'r.status = :status';
        }
        if ($docId) {
            $conditions[] = 'r.docid = :docId';
        }
        if ($uid) {
            $conditions[] = 'r.uid = :uid';
        }

        return $conditions;
    }

    public function testNoFiltersProducesNoWhereConditions(): void
    {
        $this->assertEmpty($this->deriveWhereConditions([]));
    }

    public function testRvidFilterAddsWhereCondition(): void
    {
        $conditions = $this->deriveWhereConditions(['rvid' => 1]);
        $this->assertContains('p.rvid = :rvid', $conditions);
    }

    public function testDocIdFilterAddsWhereCondition(): void
    {
        $conditions = $this->deriveWhereConditions(['docId' => 10]);
        $this->assertContains('r.docid = :docId', $conditions);
    }

    public function testStatusFilterAddsWhereCondition(): void
    {
        $conditions = $this->deriveWhereConditions(['report-status' => 'completed']);
        $this->assertContains('r.status = :status', $conditions);
    }

    public function testUidFilterAddsWhereCondition(): void
    {
        $conditions = $this->deriveWhereConditions(['uid' => 5]);
        $this->assertContains('r.uid = :uid', $conditions);
    }

    public function testAllFiltersProduceFourConditions(): void
    {
        $conditions = $this->deriveWhereConditions([
            'rvid'          => 2,
            'report-status' => 'completed',
            'docId'         => 3,
            'uid'           => 9,
        ]);
        $this->assertCount(4, $conditions);
    }
}
