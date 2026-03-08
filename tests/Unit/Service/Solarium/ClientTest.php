<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Solarium;

use App\Entity\Review;
use App\Resource\Search;
use App\Service\Solarium\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for Solarium Client service.
 *
 * Tests the non-Solr-dependent methods (getters/setters, setSearchPrams filter logic,
 * constants). Methods that require a live Solr connection (buildSearchQuery, search,
 * getAllFacets, addFilters, getSolrConfig) require integration tests.
 */
final class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        // Disable the Solarium\Client constructor (requires adapter/plugin setup)
        $this->client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods([]) // keep all real methods
            ->getMock();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTagSeparatorConstant(): void
    {
        $this->assertSame('__', Client::TAG_SEPARATOR);
    }

    // ── getJournal / setJournal ───────────────────────────────────────────────

    public function testGetJournalReturnsNullByDefault(): void
    {
        $this->assertNull($this->client->getJournal());
    }

    public function testSetJournalReturnsSelf(): void
    {
        $journal = $this->createStub(Review::class);
        $result = $this->client->setJournal($journal);
        $this->assertSame($this->client, $result);
    }

    public function testGetJournalReturnsSetJournal(): void
    {
        $journal = $this->createStub(Review::class);
        $this->client->setJournal($journal);
        $this->assertSame($journal, $this->client->getJournal());
    }

    public function testSetJournalWithNullClearsJournal(): void
    {
        $journal = $this->createStub(Review::class);
        $this->client->setJournal($journal);
        $this->client->setJournal(null);
        $this->assertNull($this->client->getJournal());
    }

    // ── getLogger / setLogger ─────────────────────────────────────────────────

    public function testGetLoggerReturnsNullByDefault(): void
    {
        $this->assertNull($this->client->getLogger());
    }

    public function testSetLoggerReturnsSelf(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $result = $this->client->setLogger($logger);
        $this->assertSame($this->client, $result);
    }

    public function testGetLoggerReturnsSetLogger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $this->client->setLogger($logger);
        $this->assertSame($logger, $this->client->getLogger());
    }

    public function testSetLoggerWithNullClearsLogger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $this->client->setLogger($logger);
        $this->client->setLogger(null);
        $this->assertNull($this->client->getLogger());
    }

    // ── getExcludedFilterTags / setExcludedFilterTags ─────────────────────────

    public function testGetExcludedFilterTagsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->client->getExcludedFilterTags());
    }

    public function testSetExcludedFilterTagsReturnsSelf(): void
    {
        $result = $this->client->setExcludedFilterTags(['tag1', 'tag2']);
        $this->assertSame($this->client, $result);
    }

    public function testGetExcludedFilterTagsReturnsSetValue(): void
    {
        $tags = ['tag0__section_id_i', 'tag1__volume_id_i'];
        $this->client->setExcludedFilterTags($tags);
        $this->assertSame($tags, $this->client->getExcludedFilterTags());
    }

    // ── getSearchPrams / setSearchPrams ───────────────────────────────────────

    public function testGetSearchPramsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->client->getSearchPrams());
    }

    public function testSetSearchPramsReturnsSelf(): void
    {
        $result = $this->client->setSearchPrams([]);
        $this->assertSame($this->client, $result);
    }

    public function testSetSearchPramsMapsValidKeysToSolrFieldNames(): void
    {
        $this->client->setSearchPrams([
            Search::SECTION_FILTER => '5',
            Search::VOLUME_FILTER  => '10',
        ]);

        $params = $this->client->getSearchPrams();

        // Keys are remapped to Solr field names via SEARCH_FILTERS_MAPPING
        $this->assertArrayHasKey('section_id_i', $params);
        $this->assertSame('5', $params['section_id_i']);
        $this->assertArrayHasKey('volume_id_i', $params);
        $this->assertSame('10', $params['volume_id_i']);
    }

    public function testSetSearchPramsIgnoresKeysNotInMapping(): void
    {
        $this->client->setSearchPrams([
            'unknown_filter'      => 'ignored_value',
            Search::SECTION_FILTER => '3',
        ]);

        $params = $this->client->getSearchPrams();

        $this->assertArrayNotHasKey('unknown_filter', $params);
        $this->assertArrayHasKey('section_id_i', $params);
    }

    public function testSetSearchPramsWithEmptyArrayResultsInEmptyParams(): void
    {
        $this->client->setSearchPrams([]);
        $this->assertSame([], $this->client->getSearchPrams());
    }

    public function testSetSearchPramsMapsAuthorFullNameFilter(): void
    {
        $this->client->setSearchPrams([
            Search::AUTHOR_FULL_NAME_FILTER => 'Dupont, Jean',
        ]);

        $params = $this->client->getSearchPrams();
        $this->assertArrayHasKey('author_fullname_t', $params);
        $this->assertSame('Dupont, Jean', $params['author_fullname_t']);
    }

    public function testSetSearchPramsMapsDocTypeFilter(): void
    {
        $this->client->setSearchPrams([
            Search::DOC_TYPE_FILTER => 'article',
        ]);

        $params = $this->client->getSearchPrams();
        $this->assertArrayHasKey('doc_type_fs', $params);
        $this->assertSame('article', $params['doc_type_fs']);
    }

    public function testSetSearchPramsMapsPublicationDateYearFilter(): void
    {
        $this->client->setSearchPrams([
            Search::PUBLICATION_DATE_YEAR_FILTER => '2022',
        ]);

        $params = $this->client->getSearchPrams();
        $this->assertArrayHasKey('publication_date_year_fs', $params);
        $this->assertSame('2022', $params['publication_date_year_fs']);
    }

    public function testSetSearchPramsCanBeCalledMultipleTimes(): void
    {
        $this->client->setSearchPrams([Search::SECTION_FILTER => '1']);
        $this->client->setSearchPrams([Search::VOLUME_FILTER => '2']);

        $params = $this->client->getSearchPrams();

        // Second call accumulates into the same array
        $this->assertArrayHasKey('section_id_i', $params);
        $this->assertArrayHasKey('volume_id_i', $params);
    }
}
