<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserRoles;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRoles entity.
 *
 * Covers:
 * - Role/board constants
 * - Constructor injection and getters (uid, rvid, roleid)
 * - setUid(), setRvid(), setRoleid() mutators
 * - setUser() fluent interface and getUser()
 * - TABLE constant
 */
final class UserRolesTest extends TestCase
{
    private UserRoles $userRoles;

    protected function setUp(): void
    {
        $this->userRoles = new UserRoles(1, 2, UserRoles::EDITORIAL_BOARD);
    }

    // ── TABLE constant ────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('USER_ROLES', UserRoles::TABLE);
    }

    // ── Board role constants ──────────────────────────────────────────────────

    public function testTechnicalBoardConstant(): void
    {
        $this->assertSame('technical_board', UserRoles::TECHNICAL_BOARD);
    }

    public function testEditorialBoardConstant(): void
    {
        $this->assertSame('editorial_board', UserRoles::EDITORIAL_BOARD);
    }

    public function testScientificBoardConstant(): void
    {
        $this->assertSame('scientific_advisory_board', UserRoles::SCIENTIFIC_BOARD);
    }

    public function testFormerMemberConstant(): void
    {
        $this->assertSame('former_member', UserRoles::FORMER_MEMBER);
    }

    // ── Role constants ────────────────────────────────────────────────────────

    public function testRoleGuestEditorConstant(): void
    {
        $this->assertSame('guest_editor', UserRoles::ROLE_GUEST_EDITOR);
    }

    public function testRoleAdvisoryBoardConstant(): void
    {
        $this->assertSame('advisory_board', UserRoles::ROLE_ADVISORY_BOARD);
    }

    public function testRoleManagingEditorConstant(): void
    {
        $this->assertSame('managing_editor', UserRoles::ROLE_MANAGING_EDITOR);
    }

    public function testRoleHandlingEditorConstant(): void
    {
        $this->assertSame('handling_editor', UserRoles::ROLE_HANDLING_EDITOR);
    }

    // ── Constructor + getters ─────────────────────────────────────────────────

    public function testGetUidReturnsConstructorValue(): void
    {
        $this->assertSame(1, $this->userRoles->getUid());
    }

    public function testGetRvidReturnsConstructorValue(): void
    {
        $this->assertSame(2, $this->userRoles->getRvid());
    }

    public function testGetRoleidReturnsConstructorValue(): void
    {
        $this->assertSame(UserRoles::EDITORIAL_BOARD, $this->userRoles->getRoleid());
    }

    public function testGetUserDefaultsToNull(): void
    {
        $this->assertNull($this->userRoles->getUser());
    }

    // ── Mutators ──────────────────────────────────────────────────────────────

    public function testSetUidUpdatesValue(): void
    {
        $this->userRoles->setUid(99);
        $this->assertSame(99, $this->userRoles->getUid());
    }

    public function testSetRvidUpdatesValue(): void
    {
        $this->userRoles->setRvid(42);
        $this->assertSame(42, $this->userRoles->getRvid());
    }

    public function testSetRoleidUpdatesValue(): void
    {
        $this->userRoles->setRoleid(UserRoles::TECHNICAL_BOARD);
        $this->assertSame(UserRoles::TECHNICAL_BOARD, $this->userRoles->getRoleid());
    }

    // ── setUser fluent interface ───────────────────────────────────────────────

    public function testSetUserReturnsSelf(): void
    {
        $user = $this->createStub(User::class);
        $result = $this->userRoles->setUser($user);
        $this->assertSame($this->userRoles, $result);
    }

    public function testSetUserStoresValue(): void
    {
        $user = $this->createStub(User::class);
        $this->userRoles->setUser($user);
        $this->assertSame($user, $this->userRoles->getUser());
    }

    public function testSetUserWithNullClearsValue(): void
    {
        $user = $this->createStub(User::class);
        $this->userRoles->setUser($user);
        $this->userRoles->setUser(null);
        $this->assertNull($this->userRoles->getUser());
    }

    // ── Different constructor args ─────────────────────────────────────────────

    public function testConstructorWithTechnicalBoard(): void
    {
        $role = new UserRoles(10, 20, UserRoles::TECHNICAL_BOARD);
        $this->assertSame(10, $role->getUid());
        $this->assertSame(20, $role->getRvid());
        $this->assertSame(UserRoles::TECHNICAL_BOARD, $role->getRoleid());
    }

    public function testAllRoleConstantsAreDistinct(): void
    {
        $roles = [
            UserRoles::TECHNICAL_BOARD,
            UserRoles::EDITORIAL_BOARD,
            UserRoles::SCIENTIFIC_BOARD,
            UserRoles::FORMER_MEMBER,
            UserRoles::ROLE_GUEST_EDITOR,
            UserRoles::ROLE_ADVISORY_BOARD,
            UserRoles::ROLE_MANAGING_EDITOR,
            UserRoles::ROLE_HANDLING_EDITOR,
        ];
        $this->assertCount(8, array_unique($roles), 'All role constants must be unique strings');
    }
}
