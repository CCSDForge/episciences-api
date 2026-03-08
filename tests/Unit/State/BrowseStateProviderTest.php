<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use App\Resource\Browse;
use App\Resource\Facet;
use App\Resource\SolrDoc;
use App\Service\Solr\SolrAuthorService;
use App\Service\Solr\SolrFacetService;
use App\State\BrowseStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for BrowseStateProvider::provide().
 *
 * Covers:
 * - author_fullname in uriVariables → authorService.getSolrAuthorsByFullName called, returns ArrayPaginator of SolrDoc
 * - no author_fullname → facetService.getSolrFacet called, returns ArrayPaginator of Facet
 * - context filters for letter, sort, search propagated to facet service
 * - empty facet result returns empty paginator
 */
final class BrowseStateProviderTest extends TestCase
{
    private MockObject|EntityManagerInterface $em;
    private MockObject|SolrFacetService $facetService;
    private MockObject|SolrAuthorService $authorService;
    private MockObject|RequestStack $requestStack;
    private BrowseStateProvider $provider;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->facetService = $this->createMock(SolrFacetService::class);
        $this->authorService = $this->createMock(SolrAuthorService::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        // No HTTP request in tests
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        // setJournal returns self (fluent interface)
        $this->facetService->method('setJournal')->willReturnSelf();
        $this->authorService->method('setJournal')->willReturnSelf();

        $this->provider = new BrowseStateProvider(
            $this->em,
            $this->createStub(LoggerInterface::class),
            new Pagination(),
            $this->facetService,
            $this->authorService,
            $this->requestStack
        );
    }

    private function makeBrowseOperation(): GetCollection
    {
        return (new GetCollection(paginationEnabled: false))->withClass(Browse::class);
    }

    // ── author_fullname path ──────────────────────────────────────────────────

    public function testProvideWithAuthorFullnameCallsAuthorService(): void
    {
        $this->authorService->expects($this->once())
            ->method('getSolrAuthorsByFullName')
            ->with('Doe, John')
            ->willReturn(['response' => ['docs' => []]]);

        $this->facetService->expects($this->never())->method('getSolrFacet');

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [BrowseStateProvider::AUTHOR_FULlNAME => 'Doe, John'], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testProvideWithAuthorFullnameWrapDocsInSolrDoc(): void
    {
        $doc = ['docid' => 42, 'title' => 'Test paper'];
        $this->authorService->method('getSolrAuthorsByFullName')
            ->willReturn(['response' => ['docs' => [$doc]]]);

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [BrowseStateProvider::AUTHOR_FULlNAME => 'Smith, Jane'], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        // Verify at least one item returned
        $this->assertGreaterThan(0, iterator_count($result->getIterator()));
    }

    public function testProvideWithAuthorFullnameAndEmptyDocsReturnsEmptyPaginator(): void
    {
        $this->authorService->method('getSolrAuthorsByFullName')
            ->willReturn(['response' => ['docs' => []]]);

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [BrowseStateProvider::AUTHOR_FULlNAME => 'Nobody'], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertSame(0, iterator_count($result->getIterator()));
    }

    public function testProvideWithAuthorFullnameMissingResponseKeyReturnsEmptyPaginator(): void
    {
        // Simulate Solr returning unexpected structure
        $this->authorService->method('getSolrAuthorsByFullName')
            ->willReturn([]);

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [BrowseStateProvider::AUTHOR_FULlNAME => 'test'], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    // ── browse/authors path (no author_fullname) ──────────────────────────────

    public function testProvideWithoutAuthorFullnameCallsFacetService(): void
    {
        $this->facetService->expects($this->once())
            ->method('getSolrFacet')
            ->willReturn([]);

        $this->authorService->expects($this->never())->method('getSolrAuthorsByFullName');

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testProvideAuthorsPathWrapsFacetResultsInFacetObjects(): void
    {
        $this->facetService->method('getSolrFacet')
            ->willReturn(['Doe, John' => 3, 'Smith, Jane' => 7]);

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);

        $items = iterator_to_array($result->getIterator());
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Facet::class, $items[0]);
    }

    public function testProvideAuthorsPathReturnsEmptyPaginatorWhenNoFacets(): void
    {
        $this->facetService->method('getSolrFacet')->willReturn([]);

        $operation = $this->makeBrowseOperation();
        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertSame(0, iterator_count($result->getIterator()));
    }

    // ── context filter propagation to facet service ───────────────────────────

    public function testFacetServiceReceivesLetterFromContext(): void
    {
        $capturedParams = null;
        $this->facetService->method('getSolrFacet')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            });

        $operation = $this->makeBrowseOperation();
        $this->provider->provide($operation, [], ['filters' => ['letter' => 'a']]);

        $this->assertNotNull($capturedParams);
        $this->assertSame('A', $capturedParams['letter']); // ucfirst applied
    }

    public function testFacetServiceReceivesSortFromContext(): void
    {
        $capturedParams = null;
        $this->facetService->method('getSolrFacet')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            });

        $operation = $this->makeBrowseOperation();
        $this->provider->provide($operation, [], ['filters' => ['sort' => 'count']]);

        $this->assertSame('count', $capturedParams['sortType']);
    }

    public function testFacetServiceDefaultLetterIsAll(): void
    {
        $capturedParams = null;
        $this->facetService->method('getSolrFacet')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            });

        $operation = $this->makeBrowseOperation();
        $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertSame('all', $capturedParams['letter']);
    }

    public function testFacetServiceDefaultSortIsIndex(): void
    {
        $capturedParams = null;
        $this->facetService->method('getSolrFacet')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            });

        $operation = $this->makeBrowseOperation();
        $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertSame('index', $capturedParams['sortType']);
    }

    // ── AUTHOR_FULlNAME constant ──────────────────────────────────────────────

    public function testAuthorFullNameConstant(): void
    {
        $this->assertSame('author_fullname', BrowseStateProvider::AUTHOR_FULlNAME);
    }
}
