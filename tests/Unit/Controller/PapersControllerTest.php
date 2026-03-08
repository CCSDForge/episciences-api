<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\PapersController;
use App\Entity\Paper;
use App\Entity\User;
use App\Exception\MissingRequestParameterException;
use App\Repository\PapersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PapersControllerTest extends TestCase
{
    private PapersController $controller;

    protected function setUp(): void
    {
        $this->controller = new PapersController();
    }

    // ── null request ──────────────────────────────────────────────────────────

    public function testNullRequestReturnsFalse(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $result = ($this->controller)($em, null);

        $this->assertFalse($result);
    }

    // ── missing documentId ────────────────────────────────────────────────────

    public function testMissingDocumentIdThrowsMissingRequestParameterException(): void
    {
        $this->expectException(MissingRequestParameterException::class);
        $this->expectExceptionMessageMatches('/documentId/');

        $em = $this->createStub(EntityManagerInterface::class);
        $request = Request::create('/api/users/1/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        // no documentId in query string

        ($this->controller)($em, $request);
    }

    // ── zero userId → early return false ──────────────────────────────────────

    public function testZeroUserIdReturnsFalse(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');

        $request = Request::create('/api/users/0/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '42']);
        $request->attributes->set('uid', 0); // uid = 0 is falsy

        $result = ($this->controller)($em, $request);

        $this->assertFalse($result);
    }

    // ── user not found ────────────────────────────────────────────────────────

    public function testUserNotFoundReturnsFalse(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['uid' => 5])->willReturn(null);

        $paperRepo = $this->createStub(PapersRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '42']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertFalse($result);
    }

    // ── paper not found ───────────────────────────────────────────────────────

    public function testPaperNotFoundReturnsFalse(): void
    {
        $user = $this->createStub(User::class);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('findOneBy')->with(['docid' => 42])->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '42']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertFalse($result);
    }

    // ── helper: build a Paper partial mock (final method uses real impl) ────────

    /**
     * getUsersAllowedToEditPaperCitations() is final and delegates to getUid/getCoAuthors/getEditors/getCopyEditors.
     * We mock those dependency methods only, letting the real final method execute.
     */
    private function makePaperMock(int $rvId, int $uid, array $coAuthors = [], array $editors = [], array $copyEditors = []): Paper
    {
        $paper = $this->getMockBuilder(Paper::class)
            ->onlyMethods(['getRvid', 'getUid', 'getCoAuthors', 'getEditors', 'getCopyEditors'])
            ->getMock();
        $paper->method('getRvid')->willReturn($rvId);
        $paper->method('getUid')->willReturn($uid);
        $paper->method('getCoAuthors')->willReturn($coAuthors);
        $paper->method('getEditors')->willReturn($editors);
        $paper->method('getCopyEditors')->willReturn($copyEditors);
        return $paper;
    }

    // ── role-based access ──────────────────────────────────────────────────────
    #[\PHPUnit\Framework\Attributes\DataProvider('roleGrantsAccessProvider')]
    public function testRoleGrantsAccess(string $role): void
    {
        // Paper owner uid=99, user uid=5 → user not in allowed list; only role matters
        $paper = $this->makePaperMock(7, 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(5);
        $user->method('hasRole')->willReturnCallback(
            static fn(string $r): bool => $r === $role
        );

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('findOneBy')->willReturn($paper);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '10']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertTrue($result, "Expected true for role $role");
    }

    public static function roleGrantsAccessProvider(): array
    {
        return [
            'secretary'        => [User::ROLE_SECRETARY],
            'administrator'    => [User::ROLE_ADMINISTRATOR],
            'editor_in_chief'  => [User::ROLE_EDITOR_IN_CHIEF],
            'root'             => [User::ROLE_ROOT],
        ];
    }

    // ── user in allowed citations list (owner uid match) ──────────────────────

    public function testUserInAllowedCitationsListReturnsTrue(): void
    {
        $userId = 5;
        // Paper uid = 5 → getUsersAllowedToEditPaperCitations() returns [5, ...]
        $paper = $this->makePaperMock(7, $userId);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($userId);
        $user->method('hasRole')->willReturn(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('findOneBy')->willReturn($paper);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '10']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertTrue($result);
    }

    // ── user is a co-author of the paper ─────────────────────────────────────

    public function testCoAuthorInAllowedCitationsListReturnsTrue(): void
    {
        $userId = 5;
        // Paper owner uid=99, user uid=5 is in coAuthors
        $paper = $this->makePaperMock(7, 99, [$userId => 'co-author-data']);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($userId);
        $user->method('hasRole')->willReturn(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('findOneBy')->willReturn($paper);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '10']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertTrue($result);
    }

    // ── no role, not in list ───────────────────────────────────────────────────

    public function testNoRoleAndNotInListReturnsFalse(): void
    {
        // Paper owner uid=99, coAuthors/editors/copyEditors don't include user uid=5
        $paper = $this->makePaperMock(7, 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(5);
        $user->method('hasRole')->willReturn(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('findOneBy')->willReturn($paper);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '10']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertFalse($result);
    }

    // ── documentId is cast to int ─────────────────────────────────────────────

    public function testDocumentIdIsCastToInt(): void
    {
        $paper = $this->makePaperMock(1, 5); // owner uid=5 matches user uid=5

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(5);
        $user->method('hasRole')->willReturn(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $paperRepo = $this->createMock(PapersRepository::class);
        // Verify docid is fetched as int 42 (not string '42')
        $paperRepo->method('findOneBy')
            ->with(['docid' => 42])
            ->willReturn($paper);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [User::class, $userRepo],
            [Paper::class, $paperRepo],
        ]);

        $request = Request::create('/api/users/5/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => '42']);
        $request->attributes->set('uid', '5');

        $result = ($this->controller)($em, $request);

        $this->assertTrue($result);
    }
}
