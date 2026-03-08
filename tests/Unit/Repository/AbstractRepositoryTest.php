<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\AppConstants;
use App\Repository\AbstractRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbstractRepository::getPaginatedItems().
 *
 * Verifies that getPaginatedItems() correctly calculates firstResult as
 * (page - 1) * itemsPerPage and passes it to the QueryBuilder.
 */
final class AbstractRepositoryTest extends TestCase
{
    private AbstractRepository $repo;

    protected function setUp(): void
    {
        // Create a concrete anonymous subclass, bypassing the Doctrine constructor.
        $this->repo = new class extends AbstractRepository {
            public function __construct()
            {
                // Intentionally skip parent::__construct (avoids Doctrine registry dependency)
            }
        };
    }

    // ── DEFAULT_MAX_RESULT constant ───────────────────────────────────────────

    public function testDefaultMaxResultConstant(): void
    {
        $this->assertSame(100, AbstractRepository::DEFAULT_MAX_RESULT);
    }

    // ── getPaginatedItems() — offset calculation ───────────────────────────────

    /**
     * For page=1, firstResult should be 0.
     */
    public function testGetPaginatedItemsPage1SetsFirstResultTo0(): void
    {
        $query = $this->createMock(AbstractQuery::class);

        $qb = $this->createMock(QueryBuilder::class);
        // Expect firstResult = (1-1)*30 = 0
        $qb->expects($this->once())
            ->method('setFirstResult')
            ->with(0)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with(AppConstants::DEFAULT_ITEM_PER_PAGE)
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $result = $this->repo->getPaginatedItems($qb);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $result);
    }

    /**
     * For page=3, itemsPerPage=10, firstResult should be (3-1)*10 = 20.
     */
    public function testGetPaginatedItemsPage3ItemsPerPage10SetsFirstResultTo20(): void
    {
        $query = $this->createMock(AbstractQuery::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('setFirstResult')
            ->with(20)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with(10)
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repo->getPaginatedItems($qb, 3, 10);
    }

    /**
     * For page=2, itemsPerPage=30 (default), firstResult should be 30.
     */
    public function testGetPaginatedItemsPage2DefaultItemsPerPageSetsFirstResultTo30(): void
    {
        $query = $this->createMock(AbstractQuery::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('setFirstResult')
            ->with(30)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with(AppConstants::DEFAULT_ITEM_PER_PAGE)
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repo->getPaginatedItems($qb, 2);
    }

    /**
     * Verify maxResults is correctly forwarded to the QueryBuilder.
     */
    public function testGetPaginatedItemsForwardsItemsPerPageToMaxResults(): void
    {
        $query = $this->createMock(AbstractQuery::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with(50)
            ->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repo->getPaginatedItems($qb, 1, 50);
    }

    /**
     * Return type is DoctrinePaginator.
     */
    public function testGetPaginatedItemsReturnsDoctrinePaginator(): void
    {
        $query = $this->createMock(AbstractQuery::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $result = $this->repo->getPaginatedItems($qb);

        $this->assertInstanceOf(\Doctrine\ORM\Tools\Pagination\Paginator::class, $result);
    }
}
