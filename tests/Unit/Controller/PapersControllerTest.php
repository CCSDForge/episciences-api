<?php

namespace App\Tests\Unit\Controller;

use App\Controller\PapersController;
use App\Entity\Paper;
use App\Entity\User;
use App\Exception\MissingRequestParameterException;
use App\Repository\PapersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PapersControllerTest extends TestCase
{
    private PapersController $controller;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|UserRepository $userRepository;
    private MockObject|PapersRepository $paperRepository;

    protected function setUp(): void
    {
        $this->controller = new PapersController();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->paperRepository = $this->createMock(PapersRepository::class);
    }

    /**
     * Build a Paper mock that does NOT stub the final getUsersAllowedToEditPaperCitations()
     * method, but stubs its internal dependencies so the real implementation can run.
     *
     * @param int   $rvId
     * @param int   $ownerUid      uid returned by Paper::getUid()
     * @param int[] $coAuthorUids  keys of co-authors array
     * @param int[] $editorUids    keys of editors array
     * @param int[] $copyEditorUids keys of copy-editors array
     */
    private function buildPaperMock(
        int $rvId,
        int $ownerUid = 0,
        array $coAuthorUids = [],
        array $editorUids = [],
        array $copyEditorUids = []
    ): MockObject {
        $paper = $this->getMockBuilder(Paper::class)
            ->onlyMethods(['getRvid', 'getUid', 'getCoAuthors', 'getEditors', 'getCopyEditors'])
            ->getMock();

        $paper->method('getRvid')->willReturn($rvId);
        $paper->method('getUid')->willReturn($ownerUid);
        $paper->method('getCoAuthors')->willReturn(array_fill_keys($coAuthorUids, true));
        $paper->method('getEditors')->willReturn(array_fill_keys($editorUids, true));
        $paper->method('getCopyEditors')->willReturn(array_fill_keys($copyEditorUids, true));

        return $paper;
    }

    // ─── Null request ───────────────────────────────────────────────────────────

    public function testReturnsFalseWhenRequestIsNull(): void
    {
        $result = $this->controller->__invoke($this->entityManager, null);

        $this->assertFalse($result);
    }

    // ─── Missing documentId ────────────────────────────────────────────────────

    public function testThrowsMissingParameterExceptionWhenDocumentIdAbsent(): void
    {
        $request = Request::create('/api/papers/citations', 'GET');

        $this->expectException(MissingRequestParameterException::class);
        $this->expectExceptionMessage('Required "documentId" parameter in "Request query" is not present.');

        $this->controller->__invoke($this->entityManager, $request);
    }

    // ─── userId = 0 ────────────────────────────────────────────────────────────

    public function testReturnsFalseWhenUserIdIsZeroInAttributes(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        // uid not set in route attributes → (int)null = 0 → skip user lookup

        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertFalse($result);
    }

    /**
     * Security: uid must come from route attributes (set by the JWT auth layer),
     * not from a user-supplied query string. After fixing $request->get('uid') →
     * $request->attributes->get('uid'), passing uid via query string must be ignored.
     */
    public function testUidFromQueryStringIsNotUsed(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42, 'uid' => 99]);
        // No attributes->set('uid') → secure path: uid is 0 → no DB lookup

        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertFalse($result);
    }

    // ─── Entity not found ──────────────────────────────────────────────────────

    public function testReturnsFalseWhenUserNotFound(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->with(['uid' => 99])->willReturn(null);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenPaperNotFound(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->with(['uid' => 99])->willReturn($user);
        $this->paperRepository->method('findOneBy')->with(['docid' => 42])->willReturn(null);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertFalse($result);
    }

    // ─── Role-based access ─────────────────────────────────────────────────────

    /**
     * @dataProvider rolesGrantingAccessDataProvider
     */
    public function testReturnsTrueWhenUserHasAllowedRole(string $role): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $rvId = 5;
        $user = $this->createMock(User::class);
        // Paper mock stubs dependencies so final getUsersAllowedToEditPaperCitations() can run
        $paper = $this->buildPaperMock($rvId, ownerUid: 0);

        $user->method('hasRole')->willReturnCallback(
            static fn($r, $rv) => $r === $role && $rv === $rvId
        );

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public static function rolesGrantingAccessDataProvider(): array
    {
        return [
            'secretary'      => [User::ROLE_SECRETARY],
            'administrator'  => [User::ROLE_ADMINISTRATOR],
            'editor_in_chief' => [User::ROLE_EDITOR_IN_CHIEF],
            'root'           => [User::ROLE_ROOT],
        ];
    }

    // ─── Editor / co-author list ───────────────────────────────────────────────

    public function testReturnsTrueWhenUserIsTheOwnerOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);
        $user->method('hasRole')->willReturn(false);

        // ownerUid = 99 → getUsersAllowedToEditPaperCitations returns [99, ...]
        $paper = $this->buildPaperMock(rvId: 5, ownerUid: 99);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsCoAuthorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->buildPaperMock(rvId: 5, ownerUid: 1, coAuthorUids: [99]);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsEditorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->buildPaperMock(rvId: 5, ownerUid: 1, editorUids: [99]);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsCopyEditorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->buildPaperMock(rvId: 5, ownerUid: 1, copyEditorUids: [99]);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenUserHasNoRoleAndNotInAnyEditorList(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 42]);
        $request->attributes->set('uid', 99);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);
        $user->method('hasRole')->willReturn(false);

        // uid 99 not in any list
        $paper = $this->buildPaperMock(rvId: 5, ownerUid: 1, coAuthorUids: [100], editorUids: [101]);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->__invoke($this->entityManager, $request);

        $this->assertFalse($result);
    }

    // ─── documentId casting ────────────────────────────────────────────────────

    public function testDocumentIdIsReadFromQueryStringAndCastToInt(): void
    {
        $request = Request::create('/api/papers/citations', 'GET', ['documentId' => 7]);
        $request->attributes->set('uid', 10);

        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(10);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->buildPaperMock(rvId: 1, ownerUid: 10);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['docid' => 7])
            ->willReturn($paper);

        $this->controller->__invoke($this->entityManager, $request);
    }
}