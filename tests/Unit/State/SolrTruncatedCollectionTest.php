<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Resource\Browse;
use App\Resource\Search;
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

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testUnpaginatedSearchWithMoreMatchesThanDocumentsIsFlagged(): void
    {
        $this->search(numFound: 5, returnedDocs: 2, pagination: self::UNPAGINATED);

        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testUnpaginatedSearchCappedByItemsPerPageIsFlagged(): void
    {
        $this->search(numFound: 3, returnedDocs: 3, pagination: self::UNPAGINATED, itemsPerPage: 2);

        $this->assertSame(2, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testCompleteUnpaginatedSearchIsNotFlagged(): void
    {
        $this->search(numFound: 2, returnedDocs: 2, pagination: self::UNPAGINATED);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testPaginatedSearchIsNotFlagged(): void
    {
        $this->search(numFound: 5, returnedDocs: 2, pagination: self::PAGINATED);

        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
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

    private function search(int $numFound, int $returnedDocs, string $pagination, ?int $itemsPerPage = null): void
    {
        $docs = array_map(static fn(int $i): array => ['docid' => $i], range(1, $returnedDocs));

        $result = $this->createStub(ResultInterface::class);
        $result->method('getData')->willReturn(['response' => ['numFound' => $numFound, 'docs' => $docs]]);

        $client = $this->createStub(Client::class);
        $client->method('setJournal')->willReturnSelf();
        $client->method('setLogger')->willReturnSelf();
        $client->method('setSearchPrams')->willReturnSelf();
        $client->method('search')->willReturn($result);

        $provider = new SearchStateProvider(
            $client,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            new Pagination(['client_enabled' => true])
        );

        $operation = new GetCollection(class: Search::class, paginationMaximumItemsPerPage: SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS);
        $filters = [Search::TERMS_PARAM => 'graph', 'pagination' => $pagination];

        if ($itemsPerPage !== null) {
            $filters['itemsPerPage'] = $itemsPerPage;
        }

        $provider->provide($operation, [], ['operation' => $operation, 'filters' => $filters, 'request' => $this->request]);
    }

    /**
     * @return object[] the returned authors
     */
    private function browseAuthors(int $facets, string $pagination, int $expectedFacetLimit): array
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

        return iterator_to_array($paginator, false);
    }
}
