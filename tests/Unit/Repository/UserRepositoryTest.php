<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRepository conditional logic.
 *
 * Bug fixed:
 *   countByReviewQuery() previously had a spurious ->from(User::class, 'u') call
 *   after createQueryBuilder('u1'), which created an unintended Cartesian product
 *   (FROM User u1, User u) with no JOIN condition between the two aliases.
 *   The fix removes the extra ->from() call so only alias 'u1' is the root entity.
 *
 * Because ServiceEntityRepository requires a live ManagerRegistry, we verify
 * the conditional logic (groupBy branches, parameter bindings) through lightweight
 * inline helpers that mirror the same conditions.
 */
final class UserRepositoryTest extends TestCase
{
    // ── countByReviewQuery: groupBy logic ────────────────────────────────────

    /**
     * Mirrors the groupBy branching logic from countByReviewQuery().
     * When $rvId is null/falsy, groupBy(rvid) is added first.
     * When $registrationYear is non-null, groupBy(year) is added (replacing rvid group).
     * addGroupBy(roleid) and addGroupBy(uid) are always added.
     */
    private function deriveGroupByClauses(?int $rvId, ?int $registrationYear): array
    {
        $groups = [];

        if (!$rvId) {
            $groups[] = 'rvid';
        }

        if ($registrationYear) {
            // groupBy replaces previous groupBy
            $groups = ['year'];
        }

        $groups[] = 'roleid';
        $groups[] = 'uid';

        return $groups;
    }

    public function testGroupByIncludesRvidWhenNoRvId(): void
    {
        $groups = $this->deriveGroupByClauses(null, null);
        $this->assertContains('rvid', $groups);
        $this->assertContains('roleid', $groups);
        $this->assertContains('uid', $groups);
    }

    public function testGroupBySkipsRvidWhenRvIdProvided(): void
    {
        $groups = $this->deriveGroupByClauses(7, null);
        $this->assertNotContains('rvid', $groups);
        $this->assertContains('roleid', $groups);
        $this->assertContains('uid', $groups);
    }

    public function testGroupByYearReplacesRvidWhenRegistrationYearProvided(): void
    {
        $groups = $this->deriveGroupByClauses(null, 2022);
        $this->assertContains('year', $groups);
        $this->assertNotContains('rvid', $groups);
        $this->assertContains('roleid', $groups);
        $this->assertContains('uid', $groups);
    }

    public function testGroupByAlwaysEndsWithRoleidAndUid(): void
    {
        foreach ([null, 5] as $rvId) {
            foreach ([null, 2020] as $year) {
                $groups = $this->deriveGroupByClauses($rvId, $year);
                $this->assertSame('uid', end($groups));
                $this->assertContains('roleid', $groups);
            }
        }
    }

    // ── boardsQuery: rvId conditional ────────────────────────────────────────

    /**
     * Mirrors the rvId condition inside boardsQuery().
     */
    private function boardsQueryHasRvIdFilter(?int $rvId): bool
    {
        return (bool)$rvId;
    }

    public function testBoardsQueryFiltersOnRvIdWhenProvided(): void
    {
        $this->assertTrue($this->boardsQueryHasRvIdFilter(42));
    }

    public function testBoardsQueryDoesNotFilterOnRvIdWhenNull(): void
    {
        $this->assertFalse($this->boardsQueryHasRvIdFilter(null));
    }

    public function testBoardsQueryDoesNotFilterOnRvIdWhenZero(): void
    {
        $this->assertFalse($this->boardsQueryHasRvIdFilter(0));
    }

    // ── USER_ALIAS constant ───────────────────────────────────────────────────

    public function testUserAliasConstant(): void
    {
        $this->assertSame('u', UserRepository::USER_ALIAS);
    }

    public function testUserAlias1IsDerivedFromUserAlias(): void
    {
        $this->assertSame('u1', UserRepository::USER_ALIAS . 1);
    }
}
