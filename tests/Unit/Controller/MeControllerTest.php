<?php

namespace App\Tests\Unit\Controller;

use App\Controller\MeController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class MeControllerTest extends TestCase
{
    private MeController $controller;
    private MockObject|Security $security;
    private MockObject|ManagerRegistry $doctrine;
    private MockObject|UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->controller = new MeController($this->security, $this->doctrine);
    }

    public function testReturnsNullWhenNoAuthenticatedUser(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->doctrine->expects($this->never())->method('getRepository');

        $result = $this->controller->__invoke();

        $this->assertNull($result);
    }

    public function testReturnsNullWhenUserNotFoundInDatabase(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(42);

        $this->security->method('getUser')->willReturn($user);

        $this->doctrine->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findOneBy')
            ->with(['uid' => 42])
            ->willReturn(null);

        $result = $this->controller->__invoke();

        $this->assertNull($result);
    }

    public function testReturnsRefreshedUserWithUpdatedRoles(): void
    {
        $journalId = 5;

        $authUser = $this->createMock(User::class);
        $authUser->method('getUid')->willReturn(42);
        $authUser->method('getCurrentJournalID')->willReturn($journalId);

        $refreshedUser = $this->createMock(User::class);
        $refreshedUser->method('getRoles')->with($journalId)->willReturn(['ROLE_EDITOR']);
        $refreshedUser->expects($this->once())->method('setRoles')->with(['ROLE_EDITOR']);
        $refreshedUser->expects($this->once())->method('setCurrentJournalID')->with($journalId);

        $this->security->method('getUser')->willReturn($authUser);

        $this->doctrine->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findOneBy')
            ->with(['uid' => 42])
            ->willReturn($refreshedUser);

        $result = $this->controller->__invoke();

        $this->assertSame($refreshedUser, $result);
    }

    public function testSetsCurrentJournalIdOnRefreshedUser(): void
    {
        $journalId = 7;

        $authUser = $this->createMock(User::class);
        $authUser->method('getUid')->willReturn(10);
        $authUser->method('getCurrentJournalID')->willReturn($journalId);

        $refreshedUser = $this->createMock(User::class);
        $refreshedUser->method('getRoles')->willReturn([]);
        $refreshedUser->method('setRoles')->willReturnSelf();
        $refreshedUser->expects($this->once())
            ->method('setCurrentJournalID')
            ->with($journalId);

        $this->security->method('getUser')->willReturn($authUser);
        $this->doctrine->method('getRepository')->willReturn($this->userRepository);
        $this->userRepository->method('findOneBy')->willReturn($refreshedUser);

        $this->controller->__invoke();
    }

    public function testRolesAreLoadedForCorrectJournal(): void
    {
        $journalId = 3;

        $authUser = $this->createMock(User::class);
        $authUser->method('getUid')->willReturn(20);
        $authUser->method('getCurrentJournalID')->willReturn($journalId);

        $refreshedUser = $this->createMock(User::class);
        $refreshedUser->expects($this->once())
            ->method('getRoles')
            ->with($journalId)
            ->willReturn(['ROLE_SECRETARY']);
        $refreshedUser->method('setRoles')->willReturnSelf();
        $refreshedUser->method('setCurrentJournalID')->willReturnSelf();

        $this->security->method('getUser')->willReturn($authUser);
        $this->doctrine->method('getRepository')->willReturn($this->userRepository);
        $this->userRepository->method('findOneBy')->willReturn($refreshedUser);

        $result = $this->controller->__invoke();

        $this->assertSame($refreshedUser, $result);
    }

    public function testWorksWithNullJournalId(): void
    {
        $authUser = $this->createMock(User::class);
        $authUser->method('getUid')->willReturn(1);
        $authUser->method('getCurrentJournalID')->willReturn(null);

        $refreshedUser = $this->createMock(User::class);
        $refreshedUser->method('getRoles')->with(null)->willReturn([]);
        $refreshedUser->method('setRoles')->willReturnSelf();
        $refreshedUser->method('setCurrentJournalID')->willReturnSelf();

        $this->security->method('getUser')->willReturn($authUser);
        $this->doctrine->method('getRepository')->willReturn($this->userRepository);
        $this->userRepository->method('findOneBy')->willReturn($refreshedUser);

        $result = $this->controller->__invoke();

        $this->assertSame($refreshedUser, $result);
    }
}