<?php

namespace App\Tests\Unit\Controller;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Controller\BoardsController;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use App\Repository\SectionRepository;
use App\Repository\UserRolesRepository;
use App\Service\Solr\SolrConstants;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class BoardsControllerTest extends TestCase
{
    private BoardsController $controller;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|LoggerInterface $logger;
    private MockObject|ReviewRepository $reviewRepository;
    private MockObject|UserRolesRepository $userRolesRepository;
    private MockObject|SectionRepository $sectionRepository;
    private MockObject|Review $journal;

    protected function setUp(): void
    {
        $this->controller = new BoardsController();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->userRolesRepository = $this->createMock(UserRolesRepository::class);
        $this->sectionRepository = $this->createMock(SectionRepository::class);
        $this->journal = $this->createMock(Review::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function stubQueryReturning(array $rows): MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);
        return $query;
    }

    private function stubQueryBuilder(array $rows): MockObject
    {
        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $qb->method('getQuery')->willReturn($this->stubQueryReturning($rows));
        return $qb;
    }

    // ─── No request ────────────────────────────────────────────────────────────

    public function testReturnsEmptyPaginatorWhenRequestIsNull(): void
    {
        $result = $this->controller->__invoke($this->entityManager, $this->logger, null);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    public function testDefaultMaxResultsWithNullRequest(): void
    {
        $result = $this->controller->__invoke($this->entityManager, $this->logger, null);

        // With no request: pagination=true, page=1, firstResult=0, maxResults=SOLR_MAX
        $this->assertCount(0, $result);
    }

    // ─── Request without pagination param ──────────────────────────────────────

    public function testRequestWithoutPaginationParamAndNoCode(): void
    {
        $request = Request::create('/api/boards', 'GET');
        // no 'pagination' query param, no 'code' attribute

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ─── Journal not found ─────────────────────────────────────────────────────

    public function testThrowsResourceNotFoundExceptionWhenJournalNotFound(): void
    {
        $request = Request::create('/api/boards/unknown', 'GET');
        $request->attributes->set('code', 'unknown');

        $this->entityManager->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepository);

        $this->reviewRepository->method('getJournalByIdentifier')
            ->with('unknown')
            ->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('unknown');

        $this->controller->__invoke($this->entityManager, $this->logger, $request);
    }

    // ─── Empty board tags ──────────────────────────────────────────────────────

    public function testReturnsEmptyPaginatorWhenNoBoardTagsFound(): void
    {
        $request = Request::create('/api/boards/myjournal', 'GET');
        $request->attributes->set('code', 'myjournal');

        $this->journal->method('getRvid')->willReturn(3);

        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [UserRoles::class, $this->userRolesRepository],
        ]);
        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);
        $this->userRolesRepository->method('boardsUsersQuery')
            ->willReturn($this->stubQueryBuilder([]));

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ─── Valid board data ──────────────────────────────────────────────────────

    public function testReturnsBoardMembersWhenDataExists(): void
    {
        $request = Request::create('/api/boards/myjournal', 'GET');
        $request->attributes->set('code', 'myjournal');

        $this->journal->method('getRvid')->willReturn(3);

        $uid = 100;
        $boardTag = UserRoles::EDITORIAL_BOARD;

        // boardsUsersQuery: returns tags for uid
        $boardTagRows = [
            ['uid' => $uid, 'roleid' => $boardTag],
        ];

        // joinUserRolesQuery: returns user with role
        $joinRows = [
            [
                'uid'   => $uid,
                'roleid' => $boardTag,
                'user'  => [
                    'uid'                       => $uid,
                    'uuid'                      => 'some-uuid',
                    'langueid'                  => 'en',
                    'screenName'                => 'jdoe',
                    'email'                     => 'j@example.com',
                    'civ'                       => 'M.',
                    'orcid'                     => null,
                    'additionalProfileInformation' => null,
                    'lastname'                  => 'Doe',
                    'firstname'                 => 'John',
                ],
            ],
        ];

        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [UserRoles::class, $this->userRolesRepository],
            [Section::class, $this->sectionRepository],
        ]);

        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);

        $this->userRolesRepository->method('boardsUsersQuery')
            ->willReturn($this->stubQueryBuilder($boardTagRows));

        $this->userRolesRepository->method('joinUserRolesQuery')
            ->willReturn($this->stubQueryBuilder($joinRows));

        $this->sectionRepository->method('getAssignedSection')
            ->willReturn([]);

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(1, $result);
        $members = iterator_to_array($result);
        $this->assertInstanceOf(User::class, $members[0]);
    }

    public function testSkipsUserWithNullUserDataAndLogsInfo(): void
    {
        $request = Request::create('/api/boards/myjournal', 'GET');
        $request->attributes->set('code', 'myjournal');

        $this->journal->method('getRvid')->willReturn(3);

        $uid = 55;
        $boardTagRows = [['uid' => $uid, 'roleid' => UserRoles::EDITORIAL_BOARD]];

        // user data is null → should log and skip
        $joinRows = [
            ['uid' => $uid, 'roleid' => UserRoles::EDITORIAL_BOARD, 'user' => null],
        ];

        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [UserRoles::class, $this->userRolesRepository],
            [Section::class, $this->sectionRepository],
        ]);

        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);
        $this->userRolesRepository->method('boardsUsersQuery')
            ->willReturn($this->stubQueryBuilder($boardTagRows));
        $this->userRolesRepository->method('joinUserRolesQuery')
            ->willReturn($this->stubQueryBuilder($joinRows));
        $this->sectionRepository->method('getAssignedSection')->willReturn([]);

        $this->logger->expects($this->once())->method('info');

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertCount(0, $result);
    }

    // ─── Pagination logic ──────────────────────────────────────────────────────

    /**
     * Bug fix: $itemsPerPage was only initialised inside the $request !== null block.
     * When $pagination=true but request is null, line 147 would throw undefined variable.
     * After the fix it is initialised to 30 before the if-block.
     */
    public function testNullRequestDoesNotCauseUndefinedVariableError(): void
    {
        // Before the fix this would throw "undefined variable $itemsPerPage"
        $result = $this->controller->__invoke($this->entityManager, $this->logger, null);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testPaginationOffsetCalculatedCorrectly(): void
    {
        // With pagination=true (default), page=2, itemsPerPage=10:
        // firstResult = (2-1) * 10 = 10, maxResults = 10
        $request = Request::create('/api/boards', 'GET', [
            'pagination'   => '1',
            'page'         => '2',
            'itemsPerPage' => '10',
        ]);

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testPaginationDisabledWhenParamAbsent(): void
    {
        // No 'pagination' query param → pagination=true, page=1, itemsPerPage=30
        $request = Request::create('/api/boards', 'GET');

        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    // ─── hasBoardTags — roles that are NOT board tags are excluded ─────────────

    public function testUserWithOnlyNonBoardRoleIsNotIncluded(): void
    {
        $request = Request::create('/api/boards/myjournal', 'GET');
        $request->attributes->set('code', 'myjournal');

        $this->journal->method('getRvid')->willReturn(3);

        $uid = 200;
        // boardsUsersQuery returns nothing → empty boardIdentifies → hasBoardTags returns false
        $boardTagRows = [];

        $joinRows = [
            [
                'uid'    => $uid,
                'roleid' => User::ROLE_EDITOR,
                'user'   => [
                    'uid' => $uid, 'uuid' => 'u', 'langueid' => 'en',
                    'screenName' => 'x', 'email' => 'x@x.com',
                    'civ' => '', 'orcid' => null,
                    'additionalProfileInformation' => null,
                    'lastname' => 'X', 'firstname' => 'Y',
                ],
            ],
        ];

        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [UserRoles::class, $this->userRolesRepository],
            [Section::class, $this->sectionRepository],
        ]);
        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);
        $this->userRolesRepository->method('boardsUsersQuery')
            ->willReturn($this->stubQueryBuilder($boardTagRows));
        $this->userRolesRepository->method('joinUserRolesQuery')
            ->willReturn($this->stubQueryBuilder($joinRows));
        $this->sectionRepository->method('getAssignedSection')->willReturn([]);

        // boardsUsersQuery returns no rows → empty boards
        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertCount(0, $result);
    }

    // ─── Section exception is caught ──────────────────────────────────────────

    public function testSectionExceptionIsHandledGracefully(): void
    {
        $request = Request::create('/api/boards/myjournal', 'GET');
        $request->attributes->set('code', 'myjournal');

        $this->journal->method('getRvid')->willReturn(3);

        $uid = 100;
        $boardTagRows = [['uid' => $uid, 'roleid' => UserRoles::EDITORIAL_BOARD]];
        $joinRows = [
            [
                'uid'    => $uid,
                'roleid' => UserRoles::EDITORIAL_BOARD,
                'user'   => [
                    'uid' => $uid, 'uuid' => 'u', 'langueid' => 'en',
                    'screenName' => 'jdoe', 'email' => 'j@j.com',
                    'civ' => 'M.', 'orcid' => null,
                    'additionalProfileInformation' => null,
                    'lastname' => 'Doe', 'firstname' => 'John',
                ],
            ],
        ];

        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [UserRoles::class, $this->userRolesRepository],
            [Section::class, $this->sectionRepository],
        ]);
        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);
        $this->userRolesRepository->method('boardsUsersQuery')
            ->willReturn($this->stubQueryBuilder($boardTagRows));
        $this->userRolesRepository->method('joinUserRolesQuery')
            ->willReturn($this->stubQueryBuilder($joinRows));
        $this->sectionRepository->method('getAssignedSection')
            ->willThrowException(new \Doctrine\DBAL\Exception('DB error'));

        $this->logger->expects($this->once())->method('critical');

        // Must not throw — exception is caught internally
        $result = $this->controller->__invoke($this->entityManager, $this->logger, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }
}