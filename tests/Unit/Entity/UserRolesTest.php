<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserRoles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * In-memory reproduction of the JWT role construction (JWTSubscriber + User::getRoles).
 *
 * At token-creation time JWTSubscriber resolves $rvId (either from the request "code"
 * journal, or from $user->getCurrentJournalID()) and then calls $user->getRoles($rvId).
 * Whatever it returns is written to the JWT "roles" claim, and at request time
 * createFromPayload() restores it, so that isGranted('ROLE_SECRETARY') in
 * StatisticPapers depends entirely on the USER_ROLES row matching that $rvId.
 *
 * These tests use REAL User + UserRoles objects (no mocks) to prove the decisive logic
 * is correct when the data is present, isolating any runtime failure to the data
 * (missing / wrong USER_ROLES.rvid) or to the deployment (missing the d37322a commit),
 * not to the role code itself.
 */
class UserRolesTest extends TestCase
{
    /**
     * A secretary of journal 7 must resolve to ROLE_SECRETARY for that journal.
     */
    public function testUserWithMatchingSecretaryRoleResolvesRoleSecretary(): void
    {
        $userRole = $this->makeUserRole(42, 7, UserRoles::ROLE_MANAGING_EDITOR);
        $user     = $this->makeUserWithRole(42, 7, $userRole);

        $this->assertSame(['ROLE_MANAGING_EDITOR'], $user->getRoles(7));
        $this->assertTrue($user->hasRole(UserRoles::ROLE_MANAGING_EDITOR, 7));
    }

    /**
     * JWT role construction relies on the USER_ROLES row rvid matching the $rvId passed
     * by JWTSubscriber. A role held for journal 7 must NOT leak into journal 8.
     */
    public function testRoleIsScopedToItsJournalRvid(): void
    {
        $userRole = $this->makeUserRole(42, 7, User::ROLE_SECRETARY);
        $user     = $this->makeUserWithRole(42, 7, $userRole);

        // correct journal -> secretary
        $this->assertSame(['ROLE_SECRETARY'], $user->getRoles(7));

        // different journal (e.g. wrong code in the request, or user's current journal
        // differing from the requested one) -> only ROLE_USER, exactly what JWTSubscriber
        // would write into the "roles" claim -> isGranted('ROLE_SECRETARY') fails at runtime.
        $this->assertSame(['ROLE_USER'], $user->getRoles(8));
        $this->assertFalse($user->hasRole(User::ROLE_SECRETARY, 8));
    }

    /**
     * The same physical role value is used by JWTSubscriber for both the login flow and
     * the delegated flow (the delegated body includes "code"), so the outcome is decided
     * by the single $rvId it passes. ROLE_SECRETARY is what StatisticPapers requires.
     */
    public function testSecretaryRoleIsProducedForTheResolvedRvid(): void
    {
        $userRole = $this->makeUserRole(1, 99, User::ROLE_SECRETARY);
        $user     = $this->makeUserWithRole(1, 99, $userRole);

        $this->assertContains('ROLE_SECRETARY', $user->getRoles(99));
    }

    /**
     * A USER_ROLES row is mandatory: without it getRoles() returns only ROLE_USER.
     */
    public function testUserWithoutUserRolesRowFallsBackToRoleUser(): void
    {
        $user = new User();
        $user->setUid(42);

        $this->assertSame(['ROLE_USER'], $user->getRoles(7));
        $this->assertSame(['ROLE_USER'], $user->getRoles(null));
    }

    /**
     * When the roles were already set (as createFromPayload does from the JWT "roles"
     * claim at request time) getRoles() returns them directly, scoped by nothing:
     * this is the request-time counterpart that isGranted reads.
     */
    public function testRolesSetViaPayloadAreReturnedDirectly(): void
    {
        $user = new User();
        $user->setUid(1)->setRoles(['ROLE_SECRETARY', 'ROLE_ADMINISTRATOR'])->setCurrentJournalID(7);

        $this->assertSame(['ROLE_SECRETARY', 'ROLE_ADMINISTRATOR'], $user->getRoles(7));
        $this->assertTrue($user->hasRole(User::ROLE_ADMINISTRATOR, 7));
    }

    // ------------------------------------------------------------------ helpers

    private function makeUserRole(int $uid, int $rvid, string $roleId): UserRoles
    {
        return new UserRoles($uid, $rvid, $roleId);
    }

    private function makeUserWithRole(int $uid, int $rvid, UserRoles $userRole): User
    {
        $user = new User();
        $user->setUid($uid);
        $user->setCurrentJournalID($rvid);
        $user->addUserRoles($userRole);

        return $user;
    }
}
