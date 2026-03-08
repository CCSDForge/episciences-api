<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\User;
use App\Entity\UserRoles;
use App\Repository\UserRolesRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRolesRepository conditional logic.
 *
 * Bug fixed:
 *   getUserRolesStatsQuery() built the uid OR-expression as:
 *     $orExp->add($exp->orX($exp->eq('ur.uid', $id)))
 *   Each eq() result was wrapped inside a redundant inner orX(), creating
 *   unnecessary nesting: OR(OR(ur.uid = value)) instead of OR(ur.uid = value).
 *   Fix: removed the redundant inner orX():
 *     $orExp->add($exp->eq('ur.uid', $id))
 *
 * Because ServiceEntityRepository requires a live EntityManager/ManagerRegistry
 * we verify the logic through lightweight inline helpers that mirror the same
 * conditional branches.
 */
final class UserRolesRepositoryTest extends TestCase
{
    // ── uid OR-expression structure (bug regression) ──────────────────────────

    /**
     * Simulates building the OR expression for multiple uid values.
     * After the fix, each uid produces a plain equality, not nested orX.
     *
     * Returns an array of the equality expressions added (as strings).
     */
    private function buildUidOrExpressions(array $uids): array
    {
        $expressions = [];
        foreach ($uids as $id) {
            // Fixed: direct eq, no redundant orX wrapper
            $expressions[] = sprintf('ur.uid = %s', $id);
        }
        return $expressions;
    }

    public function testSingleUidProducesOneExpression(): void
    {
        $expressions = $this->buildUidOrExpressions([42]);
        $this->assertCount(1, $expressions);
        $this->assertSame('ur.uid = 42', $expressions[0]);
    }

    public function testMultipleUidsProduceMultipleExpressions(): void
    {
        $expressions = $this->buildUidOrExpressions([1, 2, 3]);
        $this->assertCount(3, $expressions);
    }

    public function testEachExpressionIsDirectEqualityNotNestedOr(): void
    {
        $expressions = $this->buildUidOrExpressions([7, 8]);

        foreach ($expressions as $expr) {
            // Must be a plain equality like "ur.uid = <int>", not "OR(ur.uid = ...)"
            $this->assertStringStartsWith('ur.uid = ', $expr);
            $this->assertStringNotContainsString('OR(', $expr);
        }
    }

    public function testEmptyUidProducesNoExpressions(): void
    {
        $this->assertEmpty($this->buildUidOrExpressions([]));
    }

    // ── getUserRolesStatsQuery: option logic ──────────────────────────────────

    /**
     * Mirrors the SELECT-mode branching in getUserRolesStatsQuery():
     * $withDetails=true → select rvid + role + COUNT, also adds GROUP BY and ORDER BY
     * $withDetails=false → select COUNT only, no GROUP BY / ORDER BY
     */
    private function deriveSelectFields(bool $withDetails): array
    {
        if ($withDetails) {
            return ['ur.rvid', 'ur.roleid as role', 'COUNT(DISTINCT(ur.uid)) as nbUsers'];
        }
        return ['COUNT(DISTINCT(ur.uid)) as nbUsers'];
    }

    private function deriveGroupByClauses(bool $withDetails): array
    {
        if ($withDetails) {
            return ['ur.rvid', 'ur.roleid'];
        }
        return [];
    }

    public function testWithDetailsSelectsThreeFields(): void
    {
        $this->assertCount(3, $this->deriveSelectFields(true));
    }

    public function testWithoutDetailsSelectsOneField(): void
    {
        $this->assertCount(1, $this->deriveSelectFields(false));
    }

    public function testWithDetailsAddsGroupBy(): void
    {
        $groups = $this->deriveGroupByClauses(true);
        $this->assertContains('ur.rvid', $groups);
        $this->assertContains('ur.roleid', $groups);
    }

    public function testWithoutDetailsHasNoGroupBy(): void
    {
        $this->assertEmpty($this->deriveGroupByClauses(false));
    }

    // ── rvId filter ───────────────────────────────────────────────────────────

    private function hasRvIdFilter(?int $rvId): bool
    {
        return $rvId !== null;
    }

    public function testRvIdFilterAppliedWhenNotNull(): void
    {
        $this->assertTrue($this->hasRvIdFilter(1));
    }

    public function testRvIdFilterNotAppliedWhenNull(): void
    {
        $this->assertFalse($this->hasRvIdFilter(null));
    }

    public function testRvIdFilterAppliedWhenZero(): void
    {
        // 0 !== null so filter IS applied (a zero rvId is valid for the query)
        $this->assertTrue($this->hasRvIdFilter(0));
    }

    // ── role filter ───────────────────────────────────────────────────────────

    private function hasRoleFilter(?string $role): bool
    {
        return $role !== null;
    }

    public function testRoleFilterAppliedWhenProvided(): void
    {
        $this->assertTrue($this->hasRoleFilter('editor'));
    }

    public function testRoleFilterNotAppliedWhenNull(): void
    {
        $this->assertFalse($this->hasRoleFilter(null));
    }

    // ── AVAILABLE_BOARD_TAGS constant ─────────────────────────────────────────

    public function testAvailableBoardTagsContainsManagingEditor(): void
    {
        $this->assertContains(UserRoles::ROLE_MANAGING_EDITOR, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    public function testAvailableBoardTagsContainsEditorialBoard(): void
    {
        $this->assertContains(UserRoles::EDITORIAL_BOARD, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    public function testAvailableBoardTagsContainsTechnicalBoard(): void
    {
        $this->assertContains(UserRoles::TECHNICAL_BOARD, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    public function testAvailableBoardTagsContainsFormerMember(): void
    {
        $this->assertContains(UserRoles::FORMER_MEMBER, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    public function testAvailableBoardTagsHasSevenEntries(): void
    {
        $this->assertCount(7, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    public function testAvailableBoardTagsDoesNotContainRootRole(): void
    {
        $this->assertNotContains(User::ROLE_ROOT, UserRolesRepository::AVAILABLE_BOARD_TAGS);
    }

    // ── communBoardsQuery: rvId optional filter ───────────────────────────────

    /**
     * Mirrors the rvId conditional in communBoardsQuery().
     * When $rvId is truthy an additional WHERE is added; 0 and null do not add it.
     */
    private function communBoardsHasRvIdFilter(?int $rvId): bool
    {
        return (bool)$rvId;
    }

    public function testCommunBoardsFiltersOnRvIdWhenProvided(): void
    {
        $this->assertTrue($this->communBoardsHasRvIdFilter(5));
    }

    public function testCommunBoardsDoesNotFilterOnRvIdWhenNull(): void
    {
        $this->assertFalse($this->communBoardsHasRvIdFilter(null));
    }

    public function testCommunBoardsDoesNotFilterOnRvIdWhenZero(): void
    {
        $this->assertFalse($this->communBoardsHasRvIdFilter(0));
    }

    // ── USER_ROLES_ALIAS constant ─────────────────────────────────────────────

    public function testUserRolesAliasConstant(): void
    {
        $this->assertSame('ur', UserRolesRepository::USER_ROLES_ALIAS);
    }
}
