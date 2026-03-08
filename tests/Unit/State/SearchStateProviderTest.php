<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use App\Exception\ResourceNotFoundException;
use App\Resource\Search;
use App\Service\Solarium\Client;
use App\State\SearchStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Solarium\Core\Query\Result\ResultInterface;

final class SearchStateProviderTest extends TestCase
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private Client $client;
    private SearchStateProvider $provider;

    protected function setUp(): void
    {
        $this->em     = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->client = $this->createMock(Client::class);

        $pagination     = new Pagination();
        $this->provider = new SearchStateProvider($this->client, $this->em, $this->logger, $pagination);
    }

    // ── term validation ───────────────────────────────────────────────────────

    public function testEmptyTermsThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $operation = $this->createMock(Operation::class);
        $context   = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => '']];

        $this->provider->provide($operation, [], $context);
    }

    public function testNullTermsThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $operation = $this->createMock(Operation::class);
        $context   = ['filters' => ['pagination' => false, 'page' => 1]];

        $this->provider->provide($operation, [], $context);
    }

    public function testZeroStringTermsThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $operation = $this->createMock(Operation::class);
        $context   = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => '0']];

        $this->provider->provide($operation, [], $context);
    }

    public function testWhitespaceOnlyTermsThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $operation = $this->createMock(Operation::class);
        $context   = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => '   ']];

        $this->provider->provide($operation, [], $context);
    }

    // ── successful search ─────────────────────────────────────────────────────

    public function testValidTermsReturnsArrayPaginator(): void
    {
        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn([
            'response' => [
                'docs' => [
                    ['id' => 'doc1', 'title_t' => 'Test document'],
                ],
            ],
        ]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(30);

        $context = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => 'open access']];

        $result = $this->provider->provide($operation, [], $context);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testEmptyDocsReturnsEmptyPaginator(): void
    {
        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn(['response' => ['docs' => []]]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(30);

        $context = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => 'something']];

        $result = $this->provider->provide($operation, [], $context);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    public function testMissingResponseKeyReturnsEmptyPaginator(): void
    {
        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn([]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(30);

        $context = ['filters' => ['pagination' => false, 'page' => 1, 'terms' => 'test']];

        $result = $this->provider->provide($operation, [], $context);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    // ── pagination logic ──────────────────────────────────────────────────────

    public function testPaginationEnabledOnFirstPageReturnsResults(): void
    {
        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn(['response' => ['docs' => []]]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(10);

        $context = [
            'filters' => [
                'pagination'   => true,
                'page'         => 1,
                'itemsPerPage' => 10,
                'terms'        => 'climate',
            ],
        ];

        $result = $this->provider->provide($operation, [], $context);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }

    public function testPaginationOutOfRangeThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/out of range/i');

        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn(['response' => ['docs' => []]]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(10);

        // 0 docs total → page 3 is out of range
        $context = [
            'filters' => [
                'pagination'   => true,
                'page'         => 3,
                'itemsPerPage' => 10,
                'terms'        => 'climate',
            ],
        ];

        $this->provider->provide($operation, [], $context);
    }

    public function testItemsPerPageFromFiltersOverridesOperationDefault(): void
    {
        $solrResult = $this->createMock(ResultInterface::class);
        $solrResult->method('getData')->willReturn(['response' => ['docs' => []]]);

        $this->client->method('setJournal')->willReturnSelf();
        $this->client->method('setLogger')->willReturnSelf();
        $this->client->method('setSearchPrams')->willReturnSelf();
        $this->client->method('search')->willReturn($solrResult);

        $operation = $this->createMock(Operation::class);
        $operation->method('getPaginationMaximumItemsPerPage')->willReturn(100);

        $context = [
            'filters' => [
                'pagination'   => false,
                'page'         => 1,
                'itemsPerPage' => 5,
                'terms'        => 'biology',
            ],
        ];

        $result = $this->provider->provide($operation, [], $context);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
    }
}
