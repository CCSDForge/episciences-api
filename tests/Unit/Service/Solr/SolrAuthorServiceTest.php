<?php

namespace App\Tests\Unit\Service\Solr;

use App\Entity\Review;
use App\Service\Solr\SolrAuthorService;
use App\Service\Solr\SolrConstants;
use App\Service\Solr\SolrFacetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SolrAuthorServiceTest extends TestCase
{
    private MockObject|HttpClientInterface $httpClient;
    private MockObject|LoggerInterface $logger;
    private MockObject|ParameterBagInterface $parameterBag;
    private MockObject|SolrFacetService $facetService;
    private SolrAuthorService $authorService;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')
            ->with('app.solr.host')
            ->willReturn('http://localhost:8983/solr/episciences');

        $this->facetService = $this->createMock(SolrFacetService::class);

        $this->authorService = new SolrAuthorService(
            $this->httpClient,
            $this->logger,
            $this->parameterBag,
            $this->facetService
        );
    }

    public function testGetSolrAuthorsByFullName(): void
    {
        $expectedResponse = [
            'response' => [
                'numFound' => 2,
                'docs' => [
                    [
                        'docid' => 123,
                        'paper_title_t' => ['First Paper'],
                        'author_fullname_s' => ['Dupont, Jean', 'Martin, Pierre']
                    ],
                    [
                        'docid' => 456,
                        'paper_title_t' => ['Second Paper'],
                        'author_fullname_s' => ['Dupont, Jean']
                    ]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode($expectedResponse));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('author_fullname_t'))
            ->willReturn($response);

        $result = $this->authorService->getSolrAuthorsByFullName('Dupont, Jean');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('response', $result);
        $this->assertEquals(2, $result['response']['numFound']);
        $this->assertCount(2, $result['response']['docs']);
    }

    public function testGetSolrAuthorsByFullNameWithJournalFilter(): void
    {
        $mockJournal = $this->createMock(Review::class);
        $mockJournal->method('getRvid')->willReturn(42);

        $this->authorService->setJournal($mockJournal);

        $expectedResponse = [
            'response' => [
                'numFound' => 1,
                'docs' => [
                    ['docid' => 123, 'paper_title_t' => ['Paper Title']]
                ]
            ]
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode($expectedResponse));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('fq=revue_id_i%3A42'))
            ->willReturn($response);

        $result = $this->authorService->getSolrAuthorsByFullName('Martin, Pierre');

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['response']['numFound']);
    }

    public function testGetSolrAuthorsByFullNameEncodesSpecialCharacters(): void
    {
        $expectedResponse = ['response' => ['numFound' => 0, 'docs' => []]];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode($expectedResponse));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->callback(function ($url) {
                return str_contains($url, 'author_fullname_t%3A');
            }))
            ->willReturn($response);

        $this->authorService->getSolrAuthorsByFullName('O\'Brien, Patrick');
    }

    public function testGetCountArticlesByAuthorsFirstLetter(): void
    {
        $facetResult = [
            'A' => 15,
            'B' => 10,
            'C' => 5,
            'D' => 0
        ];

        $this->facetService
            ->expects($this->once())
            ->method('setJournal')
            ->with(null)
            ->willReturnSelf();

        $this->facetService
            ->expects($this->once())
            ->method('getSolrFacet')
            ->with([
                'facetFieldName' => 'authorFirstLetters_s',
                'minCount' => 0
            ])
            ->willReturn($facetResult);

        $result = $this->authorService->getCountArticlesByAuthorsFirstLetter();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('A', $result);
        $this->assertArrayHasKey('B', $result);
        $this->assertArrayHasKey('C', $result);
        $this->assertArrayHasKey('D', $result);
        $this->assertArrayHasKey(SolrConstants::SOLR_OTHERS_PREFIX, $result);
        $this->assertEquals(0, $result[SolrConstants::SOLR_OTHERS_PREFIX]);
    }

    public function testGetCountArticlesByAuthorsFirstLetterWithJournal(): void
    {
        $mockJournal = $this->createMock(Review::class);
        $this->authorService->setJournal($mockJournal);

        $facetResult = ['A' => 5];

        $this->facetService
            ->expects($this->once())
            ->method('setJournal')
            ->with($mockJournal)
            ->willReturnSelf();

        $this->facetService
            ->expects($this->once())
            ->method('getSolrFacet')
            ->willReturn($facetResult);

        $result = $this->authorService->getCountArticlesByAuthorsFirstLetter();

        $this->assertArrayHasKey('A', $result);
        $this->assertEquals(5, $result['A']);
        $this->assertArrayHasKey(SolrConstants::SOLR_OTHERS_PREFIX, $result);
    }

    public function testGetCountArticlesByAuthorsFirstLetterPreservesOthersIfPresent(): void
    {
        $facetResult = [
            'A' => 10,
            SolrConstants::SOLR_OTHERS_PREFIX => 3
        ];

        $this->facetService
            ->method('setJournal')
            ->willReturnSelf();

        $this->facetService
            ->method('getSolrFacet')
            ->willReturn($facetResult);

        $result = $this->authorService->getCountArticlesByAuthorsFirstLetter();

        // The + operator keeps the first value if key exists
        $this->assertEquals(3, $result[SolrConstants::SOLR_OTHERS_PREFIX]);
    }

    public function testSetAndGetJournal(): void
    {
        $mockJournal = $this->createMock(Review::class);

        $this->assertNull($this->authorService->getJournal());

        $result = $this->authorService->setJournal($mockJournal);

        $this->assertSame($this->authorService, $result);
        $this->assertSame($mockJournal, $this->authorService->getJournal());
    }

    public function testGetClient(): void
    {
        $client = $this->authorService->getClient();

        $this->assertSame($this->httpClient, $client);
    }
}
