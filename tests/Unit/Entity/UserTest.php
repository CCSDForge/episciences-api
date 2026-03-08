<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserRoles;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User entity.
 *
 * Covers:
 * - Role/entity constants
 * - Default field values (langueid = 'fr', isValid = true, roles = [])
 * - getSalt() always returns null (required by UserInterface)
 * - getRoles(): returns cached roles if set, otherwise calls rolesProcessing()
 * - rolesProcessing(): builds ROLE_<uppercase> per rvid from userRoles collection;
 *   returns ['ROLE_USER'] when rvId has no assigned roles
 * - hasRole(): delegates to getRoles() and adds ROLE_ prefix
 * - Simple setters/getters: uid, screenName, email, firstname, lastname,
 *   langueid, isValid, username, picture
 */
final class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('USER', User::TABLE);
    }

    public function testRoleRootConstant(): void
    {
        $this->assertSame('epiadmin', User::ROLE_ROOT);
    }

    public function testRoleSecretaryConstant(): void
    {
        $this->assertSame('secretary', User::ROLE_SECRETARY);
    }

    public function testRoleAdministratorConstant(): void
    {
        $this->assertSame('administrator', User::ROLE_ADMINISTRATOR);
    }

    public function testRoleEditorInChiefConstant(): void
    {
        $this->assertSame('chief_editor', User::ROLE_EDITOR_IN_CHIEF);
    }

    public function testRoleGuestEditorConstant(): void
    {
        $this->assertSame('guest_editor', User::ROLE_GUEST_EDITOR);
    }

    public function testRoleCopyEditorConstant(): void
    {
        $this->assertSame('copyeditor', User::ROLE_COPY_EDITOR);
    }

    public function testRoleEditorConstant(): void
    {
        $this->assertSame('editor', User::ROLE_EDITOR);
    }

    public function testRoleWebmasterConstant(): void
    {
        $this->assertSame('webmaster', User::ROLE_WEBMASTER);
    }

    public function testEpisciencesUidConstant(): void
    {
        $this->assertSame(666, User::EPISCIENCES_UID);
    }

    // ── Default values ────────────────────────────────────────────────────────

    public function testDefaultLangueidIsFr(): void
    {
        $this->assertSame('fr', $this->user->getLangueid());
    }

    public function testDefaultIsValidIsTrue(): void
    {
        $this->assertTrue($this->user->getIsValid());
    }

    public function testGetSaltReturnsNull(): void
    {
        $this->assertNull($this->user->getSalt());
    }

    // ── setUid / getUid ───────────────────────────────────────────────────────

    public function testSetUidReturnsSelf(): void
    {
        $result = $this->user->setUid(42);
        $this->assertSame($this->user, $result);
    }

    public function testGetUidReturnsSetValue(): void
    {
        $this->user->setUid(99);
        $this->assertSame(99, $this->user->getUid());
    }

    // ── setScreenName / getScreenName ─────────────────────────────────────────

    public function testSetScreenNameReturnsSelf(): void
    {
        $result = $this->user->setScreenName('jdoe');
        $this->assertSame($this->user, $result);
    }

    public function testGetScreenNameReturnsSetValue(): void
    {
        $this->user->setScreenName('johndoe');
        $this->assertSame('johndoe', $this->user->getScreenName());
    }

    // ── setEmail / getEmail ───────────────────────────────────────────────────

    public function testSetEmailReturnsSelf(): void
    {
        $result = $this->user->setEmail('test@example.com');
        $this->assertInstanceOf(User::class, $result);
    }

    public function testGetEmailReturnsSetValue(): void
    {
        $this->user->setEmail('user@episciences.org');
        $this->assertSame('user@episciences.org', $this->user->getEmail());
    }

    // ── setFirstname / getFirstname ───────────────────────────────────────────

    public function testGetFirstnameDefaultsToNull(): void
    {
        $this->assertNull($this->user->getFirstname());
    }

    public function testSetFirstnameReturnsSelf(): void
    {
        $result = $this->user->setFirstname('John');
        $this->assertInstanceOf(User::class, $result);
    }

    public function testGetFirstnameReturnsSetValue(): void
    {
        $this->user->setFirstname('John');
        $this->assertSame('John', $this->user->getFirstname());
    }

    // ── setLastname / getLastname ─────────────────────────────────────────────

    public function testSetLastnameReturnsSelf(): void
    {
        $result = $this->user->setLastname('Doe');
        $this->assertInstanceOf(User::class, $result);
    }

    public function testGetLastnameReturnsSetValue(): void
    {
        $this->user->setLastname('Smith');
        $this->assertSame('Smith', $this->user->getLastname());
    }

    // ── setIsValid / getIsValid ───────────────────────────────────────────────

    public function testSetIsValidReturnsSelf(): void
    {
        $result = $this->user->setIsValid(false);
        $this->assertInstanceOf(User::class, $result);
    }

    public function testGetIsValidFalse(): void
    {
        $this->user->setIsValid(false);
        $this->assertFalse($this->user->getIsValid());
    }

    // ── setLangueid / getLangueid ─────────────────────────────────────────────

    public function testSetLangueidReturnsSelf(): void
    {
        $result = $this->user->setLangueid('en');
        $this->assertSame($this->user, $result);
    }

    public function testGetLangueidReturnsSetValue(): void
    {
        $this->user->setLangueid('en');
        $this->assertSame('en', $this->user->getLangueid());
    }

    // ── getRoles() / rolesProcessing() ────────────────────────────────────────

    /**
     * When no userRoles are assigned and no rvId is provided,
     * getRoles() must fall back to ['ROLE_USER'].
     */
    public function testGetRolesReturnsRoleUserWhenNoRolesAssigned(): void
    {
        $roles = $this->user->getRoles();
        $this->assertSame(['ROLE_USER'], $roles);
    }

    /**
     * When roles are pre-set via setRoles(), getRoles() must return them
     * without calling rolesProcessing().
     */
    public function testGetRolesReturnsCachedRolesWhenSet(): void
    {
        $this->user->setRoles(['ROLE_SECRETARY', 'ROLE_EDITOR']);
        $roles = $this->user->getRoles();
        $this->assertSame(['ROLE_SECRETARY', 'ROLE_EDITOR'], $roles);
    }

    /**
     * rolesProcessing() builds ROLE_<uppercase(roleid)> indexed by rvid.
     * When a userRole for rvid=5 with roleid='editor' is added,
     * getRoles(5) must return ['ROLE_EDITOR'].
     */
    public function testGetRolesWithRvidReturnsCorrectRoles(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('editor');
        $userRole->method('getRvid')->willReturn(5);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        $roles = $this->user->getRoles(5);
        $this->assertContains('ROLE_EDITOR', $roles);
    }

    public function testGetRolesWithWrongRvidFallsBackToRoleUser(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('editor');
        $userRole->method('getRvid')->willReturn(5);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        // rvId=999 has no roles assigned
        $roles = $this->user->getRoles(999);
        $this->assertSame(['ROLE_USER'], $roles);
    }

    public function testGetRolesWithNullRvidFallsBackToRoleUser(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('secretary');
        $userRole->method('getRvid')->willReturn(3);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        // null rvId → always returns ['ROLE_USER']
        $roles = $this->user->getRoles(null);
        $this->assertSame(['ROLE_USER'], $roles);
    }

    public function testRolesProcessingUppercasesRoleid(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('secretary');
        $userRole->method('getRvid')->willReturn(10);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        $roles = $this->user->getRoles(10);
        $this->assertContains('ROLE_SECRETARY', $roles);
    }

    // ── hasRole() ─────────────────────────────────────────────────────────────

    public function testHasRoleReturnsTrueForAssignedRole(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('editor');
        $userRole->method('getRvid')->willReturn(7);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        $this->assertTrue($this->user->hasRole('editor', 7));
    }

    public function testHasRoleReturnsFalseForUnassignedRole(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('editor');
        $userRole->method('getRvid')->willReturn(7);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        $this->assertFalse($this->user->hasRole('secretary', 7));
    }

    public function testHasRoleReturnsFalseForWrongRvid(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getRoleid')->willReturn('editor');
        $userRole->method('getRvid')->willReturn(7);
        $userRole->method('getUser')->willReturn($this->user);

        $this->user->addUserRoles($userRole);

        $this->assertFalse($this->user->hasRole('editor', 99));
    }

    // ── getUserRoles / addUserRoles initial state ──────────────────────────────

    public function testGetUserRolesInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->user->getUserRoles());
    }

    public function testAddUserRolesReturnsSelf(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getUser')->willReturn(null);
        $result = $this->user->addUserRoles($userRole);
        $this->assertSame($this->user, $result);
    }

    public function testAddUserRolesDoesNotAddDuplicate(): void
    {
        $userRole = $this->createMock(UserRoles::class);
        $userRole->method('getUser')->willReturn($this->user);
        $this->user->addUserRoles($userRole);
        $this->user->addUserRoles($userRole); // same instance
        $this->assertCount(1, $this->user->getUserRoles());
    }

    // ── setRoles ──────────────────────────────────────────────────────────────

    public function testSetRolesReturnsSelf(): void
    {
        $result = $this->user->setRoles(['ROLE_EDITOR']);
        $this->assertSame($this->user, $result);
    }

    public function testSetRolesWithEmptyArrayResetsToRolesProcessing(): void
    {
        $this->user->setRoles(['ROLE_EDITOR']);
        $this->user->setRoles([]); // reset — next call will go through rolesProcessing
        $roles = $this->user->getRoles();
        $this->assertSame(['ROLE_USER'], $roles); // no userRoles assigned
    }
}
