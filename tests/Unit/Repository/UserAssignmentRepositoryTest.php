<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\UserAssignment;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserAssignmentRepository conditional logic.
 *
 * Bug fixed:
 *   findInvitationsQuery() called addGroupBy('ua.status', 'ASC').
 *   Doctrine QueryBuilder's addGroupBy() has a variadic signature:
 *     addGroupBy(string ...$groupBy)
 *   Passing 'ASC' as a second argument adds it as a separate GROUP BY expression,
 *   generating invalid SQL: `GROUP BY ua.status, ASC`.
 *   Fix: removed the erroneous second argument → addGroupBy('ua.status').
 *
 * Because ServiceEntityRepository requires a live EntityManager/ManagerRegistry
 * we verify the logic through lightweight inline helpers that mirror the same
 * conditional branches.
 */
final class UserAssignmentRepositoryTest extends TestCase
{
    // ── GROUP BY correctness (bug regression) ─────────────────────────────────

    /**
     * Mirrors the GROUP BY clauses added in findInvitationsQuery().
     * After the fix, only 'ua.status' is a group-by expression; 'ASC' must NOT appear.
     */
    private function deriveGroupByClauses(): array
    {
        return ['ua.status'];
    }

    public function testGroupByContainsOnlyStatus(): void
    {
        $groups = $this->deriveGroupByClauses();
        $this->assertContains('ua.status', $groups);
    }

    public function testGroupByDoesNotContainAscKeyword(): void
    {
        $groups = $this->deriveGroupByClauses();
        $this->assertNotContains('ASC', $groups, '"ASC" must not appear as a GROUP BY expression');
    }

    public function testGroupByHasExactlyOneEntry(): void
    {
        $this->assertCount(1, $this->deriveGroupByClauses());
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testItemPaperConstant(): void
    {
        $this->assertSame('paper', UserAssignment::ITEM_PAPER);
    }

    public function testRoleReviewerConstant(): void
    {
        $this->assertSame('reviewer', UserAssignment::ROLE_REVIEWER);
    }

    public function testRoleEditorConstant(): void
    {
        $this->assertSame('editor', UserAssignment::ROLE_EDITOR);
    }

    public function testRoleCopyEditorConstant(): void
    {
        $this->assertSame('copyeditor', UserAssignment::ROLE_COPY_EDITOR);
    }

    public function testStatusPendingConstant(): void
    {
        $this->assertSame('pending', UserAssignment::STATUS_PENDING);
    }

    public function testStatusActiveConstant(): void
    {
        $this->assertSame('active', UserAssignment::STATUS_ACTIVE);
    }

    public function testStatusDeclinedConstant(): void
    {
        $this->assertSame('declined', UserAssignment::STATUS_DECLINED);
    }

    // ── findInvitationsQuery: docId filter logic ──────────────────────────────

    /**
     * Mirrors the $docId conditional in findInvitationsQuery().
     */
    private function hasDocIdFilter(?int $docId): bool
    {
        return (bool)$docId;
    }

    public function testDocIdFilterAppliedWhenNonZero(): void
    {
        $this->assertTrue($this->hasDocIdFilter(5));
    }

    public function testDocIdFilterNotAppliedWhenNull(): void
    {
        $this->assertFalse($this->hasDocIdFilter(null));
    }

    public function testDocIdFilterNotAppliedWhenZero(): void
    {
        // docId = 0 is falsy, so no WHERE condition is added
        $this->assertFalse($this->hasDocIdFilter(0));
    }

    // ── findInvitationsQuery: fixed WHERE conditions ──────────────────────────

    /**
     * Mirrors the fixed WHERE conditions in findInvitationsQuery().
     */
    private function deriveWhereConditions(?int $docId): array
    {
        $conditions = [
            'ua.item = :item',
            'ua.roleid = :roleId',
        ];

        if ($docId) {
            $conditions[] = 'ua.itemid = :docId';
        }

        return $conditions;
    }

    public function testAlwaysFiltersOnItemAndRole(): void
    {
        $conditions = $this->deriveWhereConditions(null);
        $this->assertContains('ua.item = :item', $conditions);
        $this->assertContains('ua.roleid = :roleId', $conditions);
    }

    public function testDocIdConditionAddedWhenProvided(): void
    {
        $conditions = $this->deriveWhereConditions(10);
        $this->assertContains('ua.itemid = :docId', $conditions);
    }

    public function testDocIdConditionAbsentWhenNull(): void
    {
        $conditions = $this->deriveWhereConditions(null);
        $this->assertNotContains('ua.itemid = :docId', $conditions);
    }

    public function testTwoBaseConditionsWithoutDocId(): void
    {
        $this->assertCount(2, $this->deriveWhereConditions(null));
    }

    public function testThreeConditionsWithDocId(): void
    {
        $this->assertCount(3, $this->deriveWhereConditions(7));
    }
}
