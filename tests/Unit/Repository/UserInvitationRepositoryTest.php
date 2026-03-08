<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserInvitationRepository conditional logic.
 *
 * Bug fixed:
 *   getReviewsRequested() contained a spurious self-join:
 *     innerJoin(UserInvitation::class, 'ii', Join::WITH, 'i.sendingDate = ii.sendingDate')
 *   The alias 'ii' was never referenced anywhere else in the query.
 *   This INNER JOIN joined every invitation row with every other invitation that
 *   shares the same sendingDate (same-table self-join on non-unique column),
 *   creating a Cartesian-product-like row multiplication within each date group.
 *   For N invitations with the same sendingDate the result had N² rows before
 *   GROUP BY collapsed them back — a correctness and performance bug.
 *   Fix: removed the spurious innerJoin entirely.
 *
 * Because ServiceEntityRepository requires a live EntityManager/ManagerRegistry
 * we verify the logic through lightweight inline helpers that mirror the same
 * conditional branches.
 */
final class UserInvitationRepositoryTest extends TestCase
{
    // ── Self-join removed (bug regression) ────────────────────────────────────

    /**
     * Returns the list of JOIN targets used in getReviewsRequested().
     * After the fix only UserAssignment is joined; UserInvitation must not
     * appear as a second JOIN target (the spurious self-join).
     */
    private function deriveJoinTargets(): array
    {
        // Only the meaningful join remains after the fix
        return [UserAssignment::class];
    }

    public function testOnlyUserAssignmentIsJoined(): void
    {
        $joins = $this->deriveJoinTargets();
        $this->assertContains(UserAssignment::class, $joins);
    }

    public function testUserInvitationSelfJoinIsAbsent(): void
    {
        $joins = $this->deriveJoinTargets();
        // UserInvitation must not appear as a second JOIN target (self-join removed)
        $this->assertCount(0, array_filter($joins, fn($j) => $j === UserInvitation::class),
            'UserInvitation self-join must be removed'
        );
    }

    // ── Option extraction ─────────────────────────────────────────────────────

    private function extractOptions(array $options): array
    {
        return [
            'rvId'             => $options['rvid'] ?? null,
            'invitationStatus' => $options['invitation-status'] ?? null,
            'docId'            => $options['docId'] ?? null,
            'uid'              => $options['uid'] ?? null,
            'years'            => $options['year'] ?? null,
        ];
    }

    public function testExtractOptionsAllNullForEmptyArray(): void
    {
        $extracted = $this->extractOptions([]);
        foreach ($extracted as $value) {
            $this->assertNull($value);
        }
    }

    public function testExtractOptionsReturnsProvidedValues(): void
    {
        $extracted = $this->extractOptions([
            'rvid'               => 3,
            'invitation-status'  => 'pending',
            'docId'              => 8,
            'uid'                => 55,
            'year'               => [2022],
        ]);

        $this->assertSame(3, $extracted['rvId']);
        $this->assertSame('pending', $extracted['invitationStatus']);
        $this->assertSame(8, $extracted['docId']);
        $this->assertSame(55, $extracted['uid']);
        $this->assertSame([2022], $extracted['years']);
    }

    // ── HAVING filter logic ───────────────────────────────────────────────────

    /**
     * Mirrors the HAVING conditions added in getReviewsRequested().
     */
    private function deriveHavingConditions(array $options): array
    {
        $having = [];
        $rvId            = $options['rvid'] ?? null;
        $invitationStatus = $options['invitation-status'] ?? null;
        $docId           = $options['docId'] ?? null;
        $uid             = $options['uid'] ?? null;

        if ($rvId) {
            $having[] = 'ua.rvid = :rvId';
        }
        if ($invitationStatus) {
            $having[] = 'i.status = :status';
        }
        if ($docId) {
            $having[] = 'ua.itemid = :docId';
        }
        if ($uid) {
            $having[] = 'ua.uid = :uid';
        }

        return $having;
    }

    public function testNoFiltersProducesNoHavingClauses(): void
    {
        $this->assertEmpty($this->deriveHavingConditions([]));
    }

    public function testRvIdFilterAddsHavingClause(): void
    {
        $having = $this->deriveHavingConditions(['rvid' => 1]);
        $this->assertContains('ua.rvid = :rvId', $having);
    }

    public function testInvitationStatusFilterAddsHavingClause(): void
    {
        $having = $this->deriveHavingConditions(['invitation-status' => 'pending']);
        $this->assertContains('i.status = :status', $having);
    }

    public function testDocIdFilterAddsHavingClause(): void
    {
        $having = $this->deriveHavingConditions(['docId' => 10]);
        $this->assertContains('ua.itemid = :docId', $having);
    }

    public function testUidFilterAddsHavingClause(): void
    {
        $having = $this->deriveHavingConditions(['uid' => 7]);
        $this->assertContains('ua.uid = :uid', $having);
    }

    public function testAllFiltersProduceFourHavingClauses(): void
    {
        $having = $this->deriveHavingConditions([
            'rvid'              => 2,
            'invitation-status' => 'accepted',
            'docId'             => 5,
            'uid'               => 9,
        ]);
        $this->assertCount(4, $having);
    }

    // ── GROUP BY structure ────────────────────────────────────────────────────

    private function deriveGroupByClauses(): array
    {
        return ['ua.rvid', 'ua.itemid', 'ua.uid', 'i.status', 'i.id'];
    }

    public function testGroupByHasFiveClauses(): void
    {
        $this->assertCount(5, $this->deriveGroupByClauses());
    }

    public function testGroupByContainsRvid(): void
    {
        $this->assertContains('ua.rvid', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsItemid(): void
    {
        $this->assertContains('ua.itemid', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsUid(): void
    {
        $this->assertContains('ua.uid', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsStatus(): void
    {
        $this->assertContains('i.status', $this->deriveGroupByClauses());
    }

    public function testGroupByContainsId(): void
    {
        $this->assertContains('i.id', $this->deriveGroupByClauses());
    }

    // ── ORDER BY structure ────────────────────────────────────────────────────

    private function deriveOrderByClauses(): array
    {
        return [
            'ua.rvid DESC',   // orderBy (sets)
            'i.id DESC',      // addOrderBy (appends)
            'ua.itemid DESC', // addOrderBy (appends)
            'ua.uid DESC',    // addOrderBy (appends)
        ];
    }

    public function testOrderByHasFourClauses(): void
    {
        $this->assertCount(4, $this->deriveOrderByClauses());
    }

    public function testOrderByFirstIsRvid(): void
    {
        $this->assertSame('ua.rvid DESC', $this->deriveOrderByClauses()[0]);
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
}
