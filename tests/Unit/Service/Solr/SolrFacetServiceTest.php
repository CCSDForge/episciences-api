<?php

namespace App\Tests\Unit\Service\Solr;

use App\Entity\Review;
use App\Service\Solr\SolrConstants;
use App\Service\Solr\SolrFacetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SolrFacetServiceTest extends TestCase
{
    private MockObject|HttpClientInterface $httpClient;
    private MockObject|LoggerInterface $logger;
    private MockObject|ParameterBagInterface $parameterBag;
    private SolrFacetService $facetService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')
            ->with('app.solr.host')
            ->willReturn('http://localhost:8983/solr/episciences');

        $this->facetService = new SolrFacetService(
            $this->httpClient,
            $this->logger,
            $this->parameterBag
        );
    }

    public function testGetLettersRange(): void
    {
        $range = $this->facetService->getLettersRange();

        $this->assertCount(28, $range); // A-Z (26) + Others + All
        $this->assertEquals('A', $range[0]);
        $this->assertEquals('Z', $range[25]);
        $this->assertEquals(SolrConstants::SOLR_OTHERS_PREFIX, $range[26]);
        $this->assertEquals(SolrConstants::SOLR_ALL_PREFIX, $range[27]);
    }

    public function testGetSolrFacetWithEmptyParams(): void
    {
        $result = $this->facetService->getSolrFacet([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSolrFacetWithEmptyFacetFieldName(): void
    {
        $result = $this->facetService->getSolrFacet(['facetFieldName' => '']);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSolrFacetWithValidParams(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => [
                        'A' => 10,
                        'B' => 5,
                        'C' => 3,
                    ]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('facet.field'))
            ->willReturn($response);

        $result = $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorFirstLetters_s',
            'minCount' => 0
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('A', $result);
        $this->assertArrayHasKey('B', $result);
        $this->assertArrayHasKey('C', $result);
        $this->assertEquals(10, $result['A']);
        $this->assertEquals(5, $result['B']);
        $this->assertEquals(3, $result['C']);
    }

    public function testGetSolrFacetWithFacetSeparator(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => [
                        '123_FacetSep_Dupont, Jean' => 5,
                        '456_FacetSep_Martin, Pierre' => 3,
                    ]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorLastNameFirstNamePrefixed_fs'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('123', $result);
        $this->assertArrayHasKey('456', $result);
        $this->assertEquals([
            SolrConstants::SOLR_FACET_NAME => 'Dupont, Jean',
            SolrConstants::SOLR_FACET_COUNT => 5
        ], $result['123']);
        $this->assertEquals([
            SolrConstants::SOLR_FACET_NAME => 'Martin, Pierre',
            SolrConstants::SOLR_FACET_COUNT => 3
        ], $result['456']);
    }

    public function testGetSolrFacetWithOthersFacetSeparator(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => [
                        'Others_FacetSep_123_FacetSep_Unknown Author' => 2,
                    ]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorLastNameFirstNamePrefixed_fs'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('123', $result);
    }

    public function testGetSolrFacetWithJournalFilter(): void
    {
        $mockJournal = $this->createMock(Review::class);
        $mockJournal->method('getRvid')->willReturn(42);

        $this->facetService->setJournal($mockJournal);

        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => ['A' => 5]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('fq=revue_id_i%3A42'))
            ->willReturn($response);

        $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorFirstLetters_s'
        ]);
    }

    public function testGetSolrFacetWithLetterFilter(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => ['A' => 5]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('facet.prefix=A'))
            ->willReturn($response);

        $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorFirstLetters_s',
            'letter' => 'a'
        ]);
    }

    public function testGetSolrFacetWithSearchFilter(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => ['Dupont' => 3]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('facet.contains=Dupont'))
            ->willReturn($response);

        $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorLastNameFirstNamePrefixed_fs',
            'search' => 'Dupont'
        ]);
    }

    public function testGetSolrFacetWithSortByCount(): void
    {
        $facetData = [
            'facet_counts' => [
                'facet_fields' => [
                    'list' => ['A' => 10, 'B' => 5]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(serialize($facetData));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('facet.sort=count'))
            ->willReturn($response);

        $this->facetService->getSolrFacet([
            'facetFieldName' => 'authorFirstLetters_s',
            'sortType' => SolrConstants::SOLR_FACET_COUNT
        ]);
    }

    public function testSetAndGetJournal(): void
    {
        $mockJournal = $this->createMock(Review::class);

        $this->assertNull($this->facetService->getJournal());

        $result = $this->facetService->setJournal($mockJournal);

        $this->assertSame($this->facetService, $result);
        $this->assertSame($mockJournal, $this->facetService->getJournal());
    }
}
