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
    private const string UUID = '510815e4-f0d3-4ca1-be08-f846e3238444';

    private PapersController $controller;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;
    private \PHPUnit\Framework\MockObject\MockObject $paperRepository;

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
        $result = $this->controller->isAllowed($this->entityManager, null);

        $this->assertFalse($result);
    }

    // ─── HTTP response ─────────────────────────────────────────────────────────

    public function testInvokeReturnsTheAnswerAsJsonResponse(): void
    {
        $request = Request::create('/api/users/' . self::UUID . '/is-allowed-to-edit-citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);

        $response = $this->controller->__invoke($this->entityManager, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('false', $response->getContent());
    }

    // ─── Missing documentId ────────────────────────────────────────────────────

    public function testThrowsMissingParameterExceptionWhenDocumentIdAbsent(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET);

        $this->expectException(MissingRequestParameterException::class);
        $this->expectExceptionMessage('Required "documentId" parameter in "Request query" is not present.');

        $this->controller->isAllowed($this->entityManager, $request);
    }

    // ─── Missing or invalid uuid ───────────────────────────────────────────────

    public function testReturnsFalseWhenUuidIsMissingInAttributes(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);

        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    /**
     * The user must come from the route, not from a user-supplied query string.
     */
    public function testUuidFromQueryStringIsNotUsed(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42, 'uuid' => self::UUID]);

        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    /**
     * A numeric UID, or a value only starting like a UUID, must not be resolved to a user.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUuidDataProvider')]
    public function testReturnsFalseWithoutLookupWhenUuidIsInvalid(string $uuid): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', $uuid);

        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUuidDataProvider(): array
    {
        return [
            'numeric uid'   => ['652246'],
            'truncated'     => ['510815e4-f0d3-4ca1-be08'],
            'trailing data' => [self::UUID . '-1'],
            'not hex'       => ['510815e4-f0d3-4ca1-be08-f846e323844z'],
        ];
    }

    public function testUuidIsLookedUpInLowerCase(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', strtoupper(self::UUID));

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->expects($this->once())->method('findOneBy')->with(['uuid' => self::UUID])->willReturn(null);

        $this->assertFalse($this->controller->isAllowed($this->entityManager, $request));
    }

    // ─── Entity not found ──────────────────────────────────────────────────────

    public function testReturnsFalseWhenUserNotFound(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->with(['uuid' => self::UUID])->willReturn(null);

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenPaperNotFound(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

        $user = $this->createMock(User::class);

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->with(['uuid' => self::UUID])->willReturn($user);
        $this->paperRepository->method('findOneBy')->with(['docid' => 42])->willReturn(null);

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    // ─── Role-based access ─────────────────────────────────────────────────────

    /**
     * @dataProvider rolesGrantingAccessDataProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rolesGrantingAccessDataProvider')]
    public function testReturnsTrueWhenUserHasAllowedRole(string $role): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

        $rvId = 5;
        $user = $this->createMock(User::class);
        // Paper mock stubs dependencies so final getUsersAllowedToEditPaperCitations() can run
        $paper = $this->buildPaperMock($rvId, ownerUid: 0);

        $user->method('hasRole')->willReturnCallback(
            static fn($r, $rv): bool => $r === $role && $rv === $rvId
        );

        $this->entityManager->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Paper::class, $this->paperRepository],
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->paperRepository->method('findOneBy')->willReturn($paper);

        $result = $this->controller->isAllowed($this->entityManager, $request);

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
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

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

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsCoAuthorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

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

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsEditorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

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

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenUserIsCopyEditorOfPaper(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

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

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenUserHasNoRoleAndNotInAnyEditorList(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 42]);
        $request->attributes->set('uuid', self::UUID);

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

        $result = $this->controller->isAllowed($this->entityManager, $request);

        $this->assertFalse($result);
    }

    // ─── documentId casting ────────────────────────────────────────────────────

    public function testDocumentIdIsReadFromQueryStringAndCastToInt(): void
    {
        $request = Request::create('/api/papers/citations', \Symfony\Component\HttpFoundation\Request::METHOD_GET, ['documentId' => 7]);
        $request->attributes->set('uuid', self::UUID);

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

        $this->controller->isAllowed($this->entityManager, $request);
    }
}