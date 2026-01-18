<?php

namespace App\Tests\Unit\Service\Solr;

use App\Entity\Review;
use App\Service\Solr\SolrFeedService;
use Laminas\Feed\Writer\Entry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SolrFeedServiceTest extends TestCase
{
    private MockObject|HttpClientInterface $httpClient;
    private MockObject|LoggerInterface $logger;
    private MockObject|ParameterBagInterface $parameterBag;
    private SolrFeedService $feedService;

    protected function setUp(): void
    {
        // Simulate HTTP environment for Request::createFromGlobals()
        $_SERVER['HTTP_HOST'] = 'testjournal.episciences.org';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['SERVER_NAME'] = 'testjournal.episciences.org';
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['HTTPS'] = 'on';

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->feedService = new SolrFeedService($this->httpClient, $this->logger, $this->parameterBag);

        // Set up a mock journal to avoid null journal issues
        $mockJournal = $this->createMock(Review::class);
        $mockJournal->method('getCode')->willReturn('testjournal');
        $mockJournal->method('getName')->willReturn('Test Journal');
        $this->feedService->setJournal($mockJournal);
    }

    public function testProcessSolrFeed(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => [
                        [
                            "groupValue" => "Journal Name",
                            "doclist" => [
                                "docs" => [
                                    [
                                        "doi_s" => "10.1234/5678",
                                        "es_doc_url_s" => "http://example.com/paper/1",
                                        "paper_title_t" => ["Paper Title"],
                                        "abstract_t" => ["Abstract content"],
                                        "author_fullname_s" => ["Author One", "Author Two"],
                                        "keyword_t" => ["Keyword1", "Keyword2"],
                                        "publication_date_tdate" => "2023-01-01T00:00:00Z"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        $this->assertCount(1, $feed);

        /** @var Entry $entry */
        $entry = $feed->getEntry(0);

        $this->assertEquals("Paper Title", $entry->getTitle());
        $this->assertEquals("Abstract content", $entry->getDescription());
        $this->assertEquals("https://doi.org/10.1234/5678", $entry->getLink());

        $authors = $entry->getAuthors();
        $this->assertCount(2, $authors);
        $this->assertEquals("Author One", $authors[0]['name']);

        $categories = $entry->getCategories();
        // 1 (Journal Name) + 2 (Keywords) = 3
        $this->assertCount(3, $categories);
    }

    public function testProcessSolrFeedWithoutDoiAndAbstract(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => [
                        [
                            "groupValue" => "Journal Name",
                            "doclist" => [
                                "docs" => [
                                    [
                                        // "doi_s" => "10.1234/5678", // Missing
                                        "es_doc_url_s" => "http://example.com/paper/2",
                                        "paper_title_t" => ["Paper Title 2"],
                                        // "abstract_t" => ["Abstract content"], // Missing
                                        "author_fullname_s" => ["Author One"],
                                        "publication_date_tdate" => "2023-01-02T00:00:00Z"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        $this->assertCount(1, $feed);

        /** @var Entry $entry */
        $entry = $feed->getEntry(0);

        $this->assertEquals("Paper Title 2", $entry->getTitle());
        $this->assertEquals("...", $entry->getDescription());
        $this->assertEquals("http://example.com/paper/2", $entry->getLink());

        $categories = $entry->getCategories();
        // 1 (Journal Name)
        $this->assertCount(1, $categories);
    }

    public function testProcessSolrFeedWithEmptyGroups(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => []
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        $this->assertCount(0, $feed);
    }

    public function testProcessSolrFeedWithMissingGroupedKey(): void
    {
        $responseToArray = [];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        $this->assertCount(0, $feed);
    }

    public function testProcessSolrFeedWithMultipleDocuments(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => [
                        [
                            "groupValue" => "Journal One",
                            "doclist" => [
                                "docs" => [
                                    [
                                        "doi_s" => "10.1234/1111",
                                        "paper_title_t" => ["First Paper"],
                                        "abstract_t" => ["First Abstract"],
                                        "author_fullname_s" => ["Author A"],
                                        "publication_date_tdate" => "2023-01-01T00:00:00Z"
                                    ],
                                    [
                                        "doi_s" => "10.1234/2222",
                                        "paper_title_t" => ["Second Paper"],
                                        "abstract_t" => ["Second Abstract"],
                                        "author_fullname_s" => ["Author B"],
                                        "publication_date_tdate" => "2023-02-01T00:00:00Z"
                                    ]
                                ]
                            ]
                        ],
                        [
                            "groupValue" => "Journal Two",
                            "doclist" => [
                                "docs" => [
                                    [
                                        "doi_s" => "10.1234/3333",
                                        "paper_title_t" => ["Third Paper"],
                                        "abstract_t" => ["Third Abstract"],
                                        "author_fullname_s" => ["Author C"],
                                        "publication_date_tdate" => "2023-03-01T00:00:00Z"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        $this->assertCount(3, $feed);

        /** @var Entry $entry1 */
        $entry1 = $feed->getEntry(0);
        $this->assertEquals("First Paper", $entry1->getTitle());

        /** @var Entry $entry2 */
        $entry2 = $feed->getEntry(1);
        $this->assertEquals("Second Paper", $entry2->getTitle());

        /** @var Entry $entry3 */
        $entry3 = $feed->getEntry(2);
        $this->assertEquals("Third Paper", $entry3->getTitle());
    }

    public function testProcessSolrFeedWithMultipleKeywords(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => [
                        [
                            "groupValue" => "Journal Name",
                            "doclist" => [
                                "docs" => [
                                    [
                                        "doi_s" => "10.1234/5678",
                                        "paper_title_t" => ["Paper Title"],
                                        "abstract_t" => ["Abstract"],
                                        "author_fullname_s" => ["Author One"],
                                        "keyword_t" => ["Keyword1", "Keyword2", "Keyword3", "Keyword4"],
                                        "publication_date_tdate" => "2023-01-01T00:00:00Z"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        /** @var Entry $entry */
        $entry = $feed->getEntry(0);
        $categories = $entry->getCategories();

        // 1 (Journal Name) + 4 (Keywords) = 5
        $this->assertCount(5, $categories);
    }

    public function testProcessSolrFeedWithMultipleAuthors(): void
    {
        $responseToArray = [
            "grouped" => [
                "revue_title_s" => [
                    "groups" => [
                        [
                            "groupValue" => "Journal Name",
                            "doclist" => [
                                "docs" => [
                                    [
                                        "doi_s" => "10.1234/5678",
                                        "paper_title_t" => ["Paper Title"],
                                        "abstract_t" => ["Abstract"],
                                        "author_fullname_s" => ["Author One", "Author Two", "Author Three"],
                                        "publication_date_tdate" => "2023-01-01T00:00:00Z"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $feed = $this->feedService->processSolrFeed($responseToArray);

        /** @var Entry $entry */
        $entry = $feed->getEntry(0);
        $authors = $entry->getAuthors();

        $this->assertCount(3, $authors);
        $this->assertEquals("Author One", $authors[0]['name']);
        $this->assertEquals("Author Two", $authors[1]['name']);
        $this->assertEquals("Author Three", $authors[2]['name']);
    }

    public function testSetAndGetJournal(): void
    {
        $newFeedService = new SolrFeedService($this->httpClient, $this->logger, $this->parameterBag);

        $this->assertNull($newFeedService->getJournal());

        $mockJournal = $this->createMock(Review::class);
        $result = $newFeedService->setJournal($mockJournal);

        $this->assertSame($newFeedService, $result);
        $this->assertSame($mockJournal, $newFeedService->getJournal());
    }

    public function testGetClient(): void
    {
        $client = $this->feedService->getClient();

        $this->assertSame($this->httpClient, $client);
    }
}
