<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\AppConstants;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\News;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UnpaginatedCollectionLimitExtensionTest extends TestCase
{
    private const array WITHOUT_PAGINATION = ['filters' => ['pagination' => 'false']];

    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testSupportsCollectionWithoutPagination(): void
    {
        $this->assertTrue($this->createExtension()->supportsResult(News::class, new GetCollection(), self::WITHOUT_PAGINATION));
    }

    public function testDoesNotSupportPaginatedCollection(): void
    {
        $this->assertFalse($this->createExtension()->supportsResult(News::class, new GetCollection(), ['filters' => []]));
    }

    public function testDoesNotSupportItemOperation(): void
    {
        $this->assertFalse($this->createExtension()->supportsResult(News::class, new Get(), self::WITHOUT_PAGINATION));
    }

    public function testDoesNotSupportGraphQl(): void
    {
        $context = self::WITHOUT_PAGINATION + ['graphql_operation_name' => 'collection_query'];

        $this->assertFalse($this->createExtension()->supportsResult(News::class, new GetCollection(), $context));
    }

    public function testResultAboveTheMaximumIsTruncatedAndFlagged(): void
    {
        $items = $this->createItems(3);

        $result = $this->createExtension(['maximum_items_per_page' => 2])->getResult($this->createQueryBuilder($items, 3), News::class, new GetCollection());

        $this->assertSame(array_slice($items, 0, 2), $result);
        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testResultWithinTheMaximumIsUnchanged(): void
    {
        $items = $this->createItems(2);

        $result = $this->createExtension(['maximum_items_per_page' => 2])->getResult($this->createQueryBuilder($items, 3), News::class, new GetCollection());

        $this->assertSame($items, $result);
        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testMaximumOfTheOperationIsIgnored(): void
    {
        $extension = $this->createExtension(['maximum_items_per_page' => 5]);

        $extension->getResult($this->createQueryBuilder([], 6), News::class, new GetCollection(paginationMaximumItemsPerPage: 2));
    }

    public function testMaximumFallsBackToTheAppDefault(): void
    {
        $this->createExtension()->getResult($this->createQueryBuilder([], AppConstants::MAXIMUM_ITEMS_PER_PAGE + 1), News::class, new GetCollection());
    }

    public function testResultIsTruncatedWithoutRequest(): void
    {
        $items = $this->createItems(3);
        $extension = new UnpaginatedCollectionLimitExtension(new Pagination(['maximum_items_per_page' => 2]), $this->createStub(ManagerRegistry::class), new RequestStack());

        $result = $extension->getResult($this->createQueryBuilder($items, 3), News::class, new GetCollection());

        $this->assertSame(array_slice($items, 0, 2), $result);
    }

    /**
     * @param array<string, mixed> $paginationOptions
     */
    private function createExtension(array $paginationOptions = []): UnpaginatedCollectionLimitExtension
    {
        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        return new UnpaginatedCollectionLimitExtension(
            new Pagination(['client_enabled' => true] + $paginationOptions),
            $this->createStub(ManagerRegistry::class),
            $requestStack
        );
    }

    /**
     * Query builder without join: the result is fetched with a SQL LIMIT of $expectedMaxResults.
     *
     * @param object[] $items
     */
    private function createQueryBuilder(array $items, int $expectedMaxResults): QueryBuilder
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($items);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getDQLPart')->with('join')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setMaxResults')->with($expectedMaxResults)->willReturnSelf();

        return $queryBuilder;
    }

    /**
     * @return object[]
     */
    private function createItems(int $count): array
    {
        return array_map(static fn(): object => new stdClass(), range(1, $count));
    }
}
