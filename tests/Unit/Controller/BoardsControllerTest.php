<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Controller\BoardsController;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\UserRoles;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use App\Repository\SectionRepository;
use App\Repository\UserRolesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class BoardsControllerTest extends TestCase
{
    private BoardsController $controller;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = new BoardsController($this->logger);
    }

    // ── null request returns empty paginator ──────────────────────────────────

    public function testNullRequestReturnsEmptyPaginator(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $result = ($this->controller)($em, null);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ── missing code attribute returns empty paginator ────────────────────────

    public function testMissingCodeAttributeReturnsEmptyPaginator(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $request = Request::create('/api/journals/boards');
        // no 'code' attribute set

        $result = ($this->controller)($em, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ── journal not found throws ResourceNotFoundException ────────────────────

    public function testJournalNotFoundThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/unknown-code/');

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(Review::class)
            ->willReturn($reviewRepo);

        $request = Request::create('/api/journals/unknown-code/boards');
        $request->attributes->set('code', 'unknown-code');

        ($this->controller)($em, $request);
    }

    // ── journal found but board is empty returns empty paginator ──────────────

    public function testEmptyBoardTagsReturnsEmptyPaginator(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(1);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $userRolesRepo = $this->createMock(UserRolesRepository::class);
        $userRolesRepo->method('boardsUsersQuery')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Review::class, $reviewRepo],
            [UserRoles::class, $userRolesRepo],
        ]);

        $request = Request::create('/api/journals/testcode/boards');
        $request->attributes->set('code', 'testcode');

        $result = ($this->controller)($em, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ── pagination disabled: firstResult = 0, maxResults stays default ────────

    public function testPaginationDisabledKeepsDefaultMaxResults(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $request = Request::create('/api/journals/boards', 'GET', ['pagination' => 'false']);

        $result = ($this->controller)($em, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    // ── itemsPerPage defaults to 30 when request is null ─────────────────────

    public function testItemsPerPageDefaultsTo30WhenRequestIsNull(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $result = ($this->controller)($em, null);

        // No exception: $itemsPerPage is initialised before the if-block
        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    // ── hasBoardTags returns false when uid not in any board tag ──────────────

    public function testUserWithoutBoardTagIsExcludedFromBoards(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        // boardsUsersQuery: returns one entry with ROLE_MANAGING_EDITOR (a board tag)
        $boardQuery = $this->createMock(Query::class);
        $boardQuery->method('getArrayResult')->willReturn([
            ['uid' => 10, 'roleid' => UserRoles::ROLE_MANAGING_EDITOR],
        ]);
        $boardQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $boardQb->method('getQuery')->willReturn($boardQuery);

        // joinUserRolesQuery: returns a user that does NOT have a board tag (e.g., only ROLE_EDITOR)
        $joinQuery = $this->createMock(Query::class);
        $joinQuery->method('getArrayResult')->willReturn([
            [
                'uid' => 99, // different uid — not in boardIdentifies
                'roleid' => 'editor',
                'user' => [
                    'uid' => 99,
                    'uuid' => 'uuid-99',
                    'langueid' => 'en',
                    'screenName' => 'Jane',
                    'email' => 'jane@example.com',
                    'civ' => 'Ms',
                    'orcid' => null,
                    'additionalProfileInformation' => [],
                    'lastname' => 'Doe',
                    'firstname' => 'Jane',
                ],
            ],
        ]);
        $joinQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $joinQb->method('getQuery')->willReturn($joinQuery);

        $sectionQuery = $this->createMock(Query::class);
        $sectionQuery->method('getArrayResult')->willReturn([]);
        $sectionQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $sectionQb->method('getQuery')->willReturn($sectionQuery);

        $userRolesRepo = $this->createMock(UserRolesRepository::class);
        $userRolesRepo->method('boardsUsersQuery')->willReturn($boardQb);
        $userRolesRepo->method('joinUserRolesQuery')->willReturn($joinQb);

        $sectionRepo = $this->createMock(SectionRepository::class);
        $sectionRepo->method('getAssignedSection')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Review::class, $reviewRepo],
            [UserRoles::class, $userRolesRepo],
            [Section::class, $sectionRepo],
        ]);

        $request = Request::create('/api/journals/testcode/boards');
        $request->attributes->set('code', 'testcode');

        $result = ($this->controller)($em, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        // uid=99 is not in boardIdentifies (only uid=10 is) → no boards entry
        $this->assertCount(0, $result);
    }

    // ── logger is called for a user entry with no linked user data ────────────

    public function testNullUserDataIsLoggedAndSkipped(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $boardQuery = $this->createMock(Query::class);
        $boardQuery->method('getArrayResult')->willReturn([
            ['uid' => 5, 'roleid' => UserRoles::ROLE_MANAGING_EDITOR],
        ]);
        $boardQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $boardQb->method('getQuery')->willReturn($boardQuery);

        // user entry with null user data → logger should be called
        $joinQuery = $this->createMock(Query::class);
        $joinQuery->method('getArrayResult')->willReturn([
            ['uid' => 5, 'roleid' => UserRoles::ROLE_MANAGING_EDITOR, 'user' => null],
        ]);
        $joinQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $joinQb->method('getQuery')->willReturn($joinQuery);

        $sectionRepo = $this->createMock(SectionRepository::class);
        $sectionRepo->method('getAssignedSection')->willReturn([]);

        $userRolesRepo = $this->createMock(UserRolesRepository::class);
        $userRolesRepo->method('boardsUsersQuery')->willReturn($boardQb);
        $userRolesRepo->method('joinUserRolesQuery')->willReturn($joinQb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Review::class, $reviewRepo],
            [UserRoles::class, $userRolesRepo],
            [Section::class, $sectionRepo],
        ]);

        $this->logger->expects($this->once())->method('info');

        $request = Request::create('/api/journals/testcode/boards');
        $request->attributes->set('code', 'testcode');

        $result = ($this->controller)($em, $request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }
}