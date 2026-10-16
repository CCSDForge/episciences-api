<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\AppConstants;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Resource\Browse;
use App\Resource\Search;
use App\Resource\SolrDoc;
use App\Service\Solarium\Client;
use App\Service\Solr\SolrAuthorService;
use App\Service\Solr\SolrConstants;
use App\Service\Solr\SolrFacetService;
use App\State\BrowseStateProvider;
use App\State\SearchStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Solarium\Core\Query\Result\ResultInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Solr collections requested without pagination are flagged when capped,
 * like the Doctrine ones (see TruncatedCollectionSubscriber).
 */
class SolrTruncatedCollectionTest extends TestCase
{
    private const string UNPAGINATED = 'false';
    private const string PAGINATED = 'true';

    private Request $request;

    /** @var int[] start and rows of the last Solr search */
    private array $searchArguments = [];

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testUnpaginatedSearchAboveTheMaximumIsFlagged(): void
    {
        $max = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $paginator = $this->search(matches: $max + 1, pagination: self::UNPAGINATED);

        $this->assertSame([0, $max], $this->searchArguments);
        $this->assertCount($max, iterator_to_array($paginator));
        $this->assertSame((float)$max, $paginator->getTotalItems());
        $this->assertSame($max, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testUnpaginatedSearchCappedByItemsPerPageIsFlagged(): void
    {
        $this->search(matches: 5, pagination: self::UNPAGINATED, itemsPerPage: 2);

        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testCompleteUnpaginatedSearchIsNotFlagged(): void
    {
        $this->search(matches: 3, pagination: self::UNPAGINATED);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedSearchIsNotFlagged(): void
    {
        $this->search(matches: 5, pagination: self::PAGINATED, itemsPerPage: 2);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedSearchWithoutItemsPerPageUsesTheDefaultPageSize(): void
    {
        $paginator = $this->search(matches: 50, pagination: self::PAGINATED);

        $this->assertSame([0, AppConstants::DEFAULT_ITEM_PER_PAGE], $this->searchArguments);
        $this->assertCount(AppConstants::DEFAULT_ITEM_PER_PAGE, iterator_to_array($paginator));
        $this->assertSame(50.0, $paginator->getTotalItems());
    }

    public function testPaginatedSearchFetchesOnlyTheRequestedPage(): void
    {
        $paginator = $this->search(matches: 5, pagination: self::PAGINATED, itemsPerPage: 2, page: 2);

        $this->assertSame([2, 2], $this->searchArguments); // start, rows sent to Solr
        $this->assertSame([3, 4], array_map(static fn(SolrDoc $doc): int => $doc->getDocid(), iterator_to_array($paginator, false)));
        $this->assertSame(5.0, $paginator->getTotalItems()); // Solr numFound
        $this->assertSame(2.0, $paginator->getCurrentPage());
        $this->assertSame(3.0, $paginator->getLastPage());
    }

    public function testUnpaginatedAuthorsAboveTheMaximumAreTruncatedAndFlagged(): void
    {
        $max = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $authors = $this->browseAuthors(facets: $max + 1, pagination: self::UNPAGINATED, expectedFacetLimit: $max + 1);

        $this->assertCount($max, $authors);
        $this->assertSame($max, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testCompleteUnpaginatedAuthorsAreNotFlagged(): void
    {
        $max = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $authors = $this->browseAuthors(facets: $max, pagination: self::UNPAGINATED, expectedFacetLimit: $max + 1);

        $this->assertCount($max, $authors);
        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedAuthorsKeepTheFacetLimit(): void
    {
        $max = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $this->browseAuthors(facets: $max, pagination: self::PAGINATED, expectedFacetLimit: $max);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    /**
     * Solr stub returning the requested slice ($start, $rows) of $matches documents.
     *
     * @return PaginatorInterface<SolrDoc>
     */
    private function search(int $matches, string $pagination, ?int $itemsPerPage = null, int $page = 1): PaginatorInterface
    {
        $docs = array_map(static fn(int $i): array => ['docid' => $i], range(1, $matches));

        $client = $this->createStub(Client::class);
        $client->method('setJournal')->willReturnSelf();
        $client->method('setLogger')->willReturnSelf();
        $client->method('setSearchPrams')->willReturnSelf();
        $client->method('search')->willReturnCallback(function (string $terms, int $start, int $rows) use ($docs, $matches): ResultInterface {
            $this->searchArguments = [$start, $rows];

            $result = $this->createStub(ResultInterface::class);
            $result->method('getData')->willReturn(['response' => ['numFound' => $matches, 'docs' => array_slice($docs, $start, $rows)]]);

            return $result;
        });

        $provider = new SearchStateProvider(
            $client,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true])
        );

        $operation = new GetCollection(class: Search::class, paginationMaximumItemsPerPage: SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS);
        $filters = [Search::TERMS_PARAM => 'graph', 'pagination' => $pagination, 'page' => $page];

        if ($itemsPerPage !== null) {
            $filters['itemsPerPage'] = $itemsPerPage;
        }

        $paginator = $provider->provide($operation, [], ['operation' => $operation, 'filters' => $filters, 'request' => $this->request]);
        $this->assertInstanceOf(PaginatorInterface::class, $paginator);

        return $paginator;
    }

    public function testPaginatedAuthorsWithoutItemsPerPageUseTheDefaultPageSize(): void
    {
        $authors = $this->browseAuthors(facets: 50, pagination: self::PAGINATED, expectedFacetLimit: SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS, expectedTotal: 50);

        $this->assertCount(AppConstants::DEFAULT_ITEM_PER_PAGE, $authors);
    }

    public function testUnpaginatedAuthorsTotalExcludesTheExtraAuthor(): void
    {
        $max = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $this->browseAuthors(facets: $max + 1, pagination: self::UNPAGINATED, expectedFacetLimit: $max + 1, expectedTotal: $max);
    }

    /**
     * @return object[] the returned authors
     */
    private function browseAuthors(int $facets, string $pagination, int $expectedFacetLimit, ?int $expectedTotal = null): array
    {
        $facetService = $this->createMock(SolrFacetService::class);
        $facetService->method('setJournal')->willReturnSelf();
        $facetService->expects($this->once())
            ->method('getSolrFacet')
            ->with($this->callback(static fn(array $params): bool => $params['facetLimit'] === $expectedFacetLimit))
            ->willReturn(array_fill_keys(array_map(static fn(int $i): string => 'author' . $i, range(1, $facets)), 1));

        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        $provider = new BrowseStateProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true]),
            $facetService,
            $this->createStub(SolrAuthorService::class),
            $requestStack
        );

        $operation = new GetCollection(class: Browse::class);

        $paginator = $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => $pagination], 'request' => $this->request]);

        $this->assertInstanceOf(PaginatorInterface::class, $paginator);

        if ($expectedTotal !== null) {
            $this->assertSame((float)$expectedTotal, $paginator->getTotalItems());
        }

        return iterator_to_array($paginator, false);
    }
}
