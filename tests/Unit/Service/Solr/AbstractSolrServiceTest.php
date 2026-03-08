<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Solr;

use App\Entity\Review;
use App\Service\Solr\AbstractSolrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Concrete subclass used to expose protected methods for testing.
 */
final class ConcreteSolrService extends AbstractSolrService
{
    public function callBuildSolrUrl(string $endpoint, array $params, array $filters = []): string
    {
        return $this->buildSolrUrl($endpoint, $params, $filters);
    }

    public function callGetJournalFilter(): array
    {
        return $this->getJournalFilter();
    }
}

/**
 * Unit tests for AbstractSolrService.
 *
 * Covers:
 * - buildSolrUrl(): URL construction, query param encoding, filter appending
 * - getJournalFilter(): returns [] without journal, returns rvid filter with journal
 * - setJournal() / getJournal(): fluent interface
 * - getClient(): returns injected HttpClient
 */
final class AbstractSolrServiceTest extends TestCase
{
    private ConcreteSolrService $service;
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);

        $paramBag = $this->createMock(ParameterBagInterface::class);
        $paramBag->method('get')
            ->with('app.solr.host')
            ->willReturn('http://solr.example.com:8983/solr/episciences');

        $this->service = new ConcreteSolrService(
            $this->httpClient,
            $this->createStub(LoggerInterface::class),
            $paramBag
        );
    }

    // ── getClient ─────────────────────────────────────────────────────────────

    public function testGetClientReturnsInjectedClient(): void
    {
        $this->assertSame($this->httpClient, $this->service->getClient());
    }

    // ── setJournal / getJournal ───────────────────────────────────────────────

    public function testGetJournalDefaultsToNull(): void
    {
        $this->assertNull($this->service->getJournal());
    }

    public function testSetJournalFluentInterface(): void
    {
        $journal = $this->createStub(Review::class);
        $result = $this->service->setJournal($journal);
        $this->assertSame($this->service, $result, 'setJournal() must return static');
    }

    public function testSetJournalStoresValue(): void
    {
        $journal = $this->createStub(Review::class);
        $this->service->setJournal($journal);
        $this->assertSame($journal, $this->service->getJournal());
    }

    public function testSetJournalWithNullResetsToNull(): void
    {
        $journal = $this->createStub(Review::class);
        $this->service->setJournal($journal);
        $this->service->setJournal(null);
        $this->assertNull($this->service->getJournal());
    }

    // ── getJournalFilter ──────────────────────────────────────────────────────

    public function testGetJournalFilterReturnsEmptyArrayWithoutJournal(): void
    {
        $this->assertSame([], $this->service->callGetJournalFilter());
    }

    public function testGetJournalFilterReturnsRvidFilterWhenJournalSet(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(42);

        $this->service->setJournal($journal);
        $filters = $this->service->callGetJournalFilter();

        $this->assertCount(1, $filters);
        $this->assertSame('revue_id_i:42', $filters[0]);
    }

    public function testGetJournalFilterRvidIsCorrectlyFormatted(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $this->service->setJournal($journal);
        $filters = $this->service->callGetJournalFilter();

        $this->assertStringStartsWith('revue_id_i:', $filters[0]);
        $this->assertStringEndsWith(':7', $filters[0]);
    }

    // ── buildSolrUrl ──────────────────────────────────────────────────────────

    public function testBuildSolrUrlContainsSolrHost(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*']);
        $this->assertStringContainsString('solr.example.com', $url);
    }

    public function testBuildSolrUrlAppendsEndpoint(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', []);
        $this->assertStringContainsString('/select/', $url);
    }

    public function testBuildSolrUrlEncodesQueryParams(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*', 'rows' => 10]);
        $this->assertStringContainsString('q=%2A%3A%2A', $url);
        $this->assertStringContainsString('rows=10', $url);
    }

    public function testBuildSolrUrlAppendsFilters(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*'], ['revue_id_i:42']);
        $this->assertStringContainsString('fq=', $url);
        $this->assertStringContainsString('revue_id_i%3A42', $url);
    }

    public function testBuildSolrUrlWithMultipleFilters(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*'], [
            'revue_id_i:42',
            'status_i:1',
        ]);

        // Both filters must appear as separate fq= parameters
        $this->assertSame(2, substr_count($url, 'fq='));
    }

    public function testBuildSolrUrlWithNoFiltersHasNoFqParam(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*'], []);
        $this->assertStringNotContainsString('fq=', $url);
    }

    public function testBuildSolrUrlUsesRfc3986Encoding(): void
    {
        // RFC3986 encodes space as %20, not +
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => 'hello world']);
        $this->assertStringContainsString('%20', $url);
        $this->assertStringNotContainsString('q=hello+world', $url);
    }

    public function testBuildSolrUrlStartsWithBaseUrl(): void
    {
        $url = $this->service->callBuildSolrUrl('/select/', ['q' => '*:*']);
        $this->assertStringStartsWith('http://solr.example.com:8983/solr/episciences/select/', $url);
    }
}
