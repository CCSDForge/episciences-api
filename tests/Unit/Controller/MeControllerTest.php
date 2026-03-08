<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\MeController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class MeControllerTest extends TestCase
{
    private function makeController(Security $security, ManagerRegistry $doctrine): MeController
    {
        return new MeController($security, $doctrine);
    }

    // ── null user → null ──────────────────────────────────────────────────────

    public function testNullUserReturnsNull(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getRepository');

        $controller = $this->makeController($security, $doctrine);

        $this->assertNull($controller->__invoke());
    }

    // ── refreshed user not found → null ───────────────────────────────────────

    public function testRefreshedUserNotFoundReturnsNull(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(42);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->with(['uid' => 42])->willReturn(null);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getRepository')->with(User::class)->willReturn($repo);

        $controller = $this->makeController($security, $doctrine);

        $this->assertNull($controller->__invoke());
    }

    // ── success: returns refreshed user ──────────────────────────────────────

    public function testReturnsRefreshedUserWithRolesAndJournalId(): void
    {
        $journalId = 7;
        $uid = 42;

        $jwtUser = $this->createMock(User::class);
        $jwtUser->method('getUid')->willReturn($uid);
        $jwtUser->method('getCurrentJournalID')->willReturn($journalId);

        $refreshedUser = $this->createMock(User::class);
        $refreshedUser->method('getRoles')->with($journalId)->willReturn(['ROLE_USER', 'ROLE_SECRETARY']);
        $refreshedUser->expects($this->once())->method('setRoles')->with(['ROLE_USER', 'ROLE_SECRETARY']);
        $refreshedUser->expects($this->once())->method('setCurrentJournalID')->with($journalId);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($jwtUser);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->with(['uid' => $uid])->willReturn($refreshedUser);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getRepository')->with(User::class)->willReturn($repo);

        $controller = $this->makeController($security, $doctrine);

        $result = $controller->__invoke();

        $this->assertSame($refreshedUser, $result);
    }

    // ── roles are fetched using the current journal id ────────────────────────

    public function testRolesAreFetchedWithCurrentJournalId(): void
    {
        $journalId = 99;

        $jwtUser = $this->createMock(User::class);
        $jwtUser->method('getUid')->willReturn(1);
        $jwtUser->method('getCurrentJournalID')->willReturn($journalId);

        $refreshedUser = $this->createMock(User::class);
        // getRoles must receive the journal ID from the JWT user
        $refreshedUser->expects($this->once())
            ->method('getRoles')
            ->with($journalId)
            ->willReturn(['ROLE_EDITOR']);
        $refreshedUser->method('setRoles');
        $refreshedUser->method('setCurrentJournalID');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($jwtUser);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->willReturn($refreshedUser);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getRepository')->willReturn($repo);

        $controller = $this->makeController($security, $doctrine);
        $controller->__invoke();
    }
}
