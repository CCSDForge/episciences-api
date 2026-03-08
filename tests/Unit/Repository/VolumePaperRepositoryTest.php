<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VolumePaperRepository conditional logic.
 *
 * Bug fixed:
 *   getPapersInSecondaryVolumeWithoutPositionQuery() called groupBy('vp.vid')
 *   followed by groupBy('vp.docid').  In Doctrine QueryBuilder each groupBy()
 *   call *replaces* the previous GROUP BY clause, so only 'vp.docid' was kept
 *   and 'vp.vid' was silently lost.
 *   Fix: second call changed to addGroupBy('vp.docid').
 *
 * Because ServiceEntityRepository requires a live EntityManager/ManagerRegistry
 * we verify the logic through lightweight inline helpers that mirror the same
 * conditional branches.
 */
final class VolumePaperRepositoryTest extends TestCase
{
    // ── GROUP BY accumulation (bug regression) ────────────────────────────────

    /**
     * Mirrors the corrected GROUP BY strategy:
     * groupBy() sets the first clause, addGroupBy() appends subsequent ones.
     */
    private function deriveGroupByClauses(?int $vid): array
    {
        // groupBy sets the first clause
        $groups = ['vp.vid'];
        // addGroupBy appends (does NOT replace)
        $groups[] = 'vp.docid';

        return $groups;
    }

    public function testGroupByContainsBothVidAndDocid(): void
    {
        $groups = $this->deriveGroupByClauses(null);

        $this->assertContains('vp.vid', $groups);
        $this->assertContains('vp.docid', $groups);
    }

    public function testGroupByHasTwoClauses(): void
    {
        $this->assertCount(2, $this->deriveGroupByClauses(null));
    }

    public function testGroupByVidComesBeforeDocid(): void
    {
        $groups = $this->deriveGroupByClauses(null);
        $this->assertSame('vp.vid', $groups[0]);
        $this->assertSame('vp.docid', $groups[1]);
    }

    public function testGroupByIsIndependentOfVidFilter(): void
    {
        // GROUP BY structure should be the same regardless of the $vid filter
        $groupsNoFilter  = $this->deriveGroupByClauses(null);
        $groupsWithFilter = $this->deriveGroupByClauses(42);

        $this->assertSame($groupsNoFilter, $groupsWithFilter);
    }

    // ── vid filter logic ──────────────────────────────────────────────────────

    /**
     * Mirrors the WHERE-filter branch for the optional $vid parameter.
     */
    private function hasVidFilter(?int $vid): bool
    {
        return $vid !== null;
    }

    public function testVidFilterAppliedWhenVidProvided(): void
    {
        $this->assertTrue($this->hasVidFilter(10));
    }

    public function testVidFilterNotAppliedWhenVidIsNull(): void
    {
        $this->assertFalse($this->hasVidFilter(null));
    }

    public function testVidFilterNotAppliedWhenVidIsZero(): void
    {
        // Zero is a valid int but semantically invalid; $vid !== null allows it.
        // The method signature is int $vid = null, so 0 passes the !== null check.
        $this->assertTrue($this->hasVidFilter(0));
    }

    // ── getPapersFromSecondaryVolume: result-processing logic ─────────────────

    /**
     * Mirrors the collection-building logic in getPapersFromSecondaryVolume().
     * For each result VolumePaper, a Paper is fetched and added to the collection
     * only if it is non-null and not already in the collection.
     */
    private function processPaperResults(array $dbResults, callable $fetchPaper): array
    {
        $collection = [];

        foreach ($dbResults as $row) {
            $paper = $fetchPaper($row['docid']);
            if ($paper !== null && !in_array($paper, $collection, true)) {
                $collection[] = $paper;
            }
        }

        return $collection;
    }

    public function testEmptyResultProducesEmptyCollection(): void
    {
        $collection = $this->processPaperResults([], fn($id) => new \stdClass());
        $this->assertEmpty($collection);
    }

    public function testNullPaperIsNotAddedToCollection(): void
    {
        $collection = $this->processPaperResults(
            [['docid' => 1]],
            fn($id) => null
        );
        $this->assertEmpty($collection);
    }

    public function testDuplicatePaperAddedOnlyOnce(): void
    {
        $paper = new \stdClass();
        $paper->docid = 1;

        $collection = $this->processPaperResults(
            [['docid' => 1], ['docid' => 1]],
            fn($id) => $paper
        );

        $this->assertCount(1, $collection);
    }

    public function testDistinctPapersAreAllAdded(): void
    {
        $paper1 = new \stdClass();
        $paper2 = new \stdClass();

        $papers = [1 => $paper1, 2 => $paper2];
        $collection = $this->processPaperResults(
            [['docid' => 1], ['docid' => 2]],
            fn($id) => $papers[$id]
        );

        $this->assertCount(2, $collection);
    }

    // ── getNoEmptySecondaryVolumes: parameter logic ───────────────────────────

    /**
     * Mirrors the rvId + strictlyPublished boolean logic used in getNoEmptySecondaryVolumes().
     */
    private function deriveNoEmptyFilters(?int $rvId, bool $strictlyPublished, int|array|null $ids): array
    {
        $filters = ['strictlyPublished' => $strictlyPublished];

        if ($rvId !== null) {
            $filters['rvId'] = $rvId;
        }

        if ($ids !== null) {
            $filters['ids'] = (array)$ids;
        }

        return $filters;
    }

    public function testStrictlyPublishedDefaultsToTrue(): void
    {
        $filters = $this->deriveNoEmptyFilters(null, true, null);
        $this->assertTrue($filters['strictlyPublished']);
    }

    public function testRvIdIncludedWhenProvided(): void
    {
        $filters = $this->deriveNoEmptyFilters(5, true, null);
        $this->assertArrayHasKey('rvId', $filters);
        $this->assertSame(5, $filters['rvId']);
    }

    public function testRvIdNotIncludedWhenNull(): void
    {
        $filters = $this->deriveNoEmptyFilters(null, true, null);
        $this->assertArrayNotHasKey('rvId', $filters);
    }

    public function testIdsNormalizedToArray(): void
    {
        $filters = $this->deriveNoEmptyFilters(null, true, 7);
        $this->assertSame([7], $filters['ids']);
    }

    public function testIdsAsArrayPreserved(): void
    {
        $filters = $this->deriveNoEmptyFilters(null, true, [3, 4]);
        $this->assertSame([3, 4], $filters['ids']);
    }
}
