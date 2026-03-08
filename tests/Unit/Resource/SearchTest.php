<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Search;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Search resource constants.
 *
 * The Search class defines the Solr search API contract: filter names,
 * mapping to Solr field names, and default query terms. Verifying these
 * constants prevents accidental regressions when field names change.
 */
final class SearchTest extends TestCase
{
    // ── Default terms ─────────────────────────────────────────────────────────

    public function testDefaultTermsIsWildcard(): void
    {
        $this->assertSame('*:*', Search::DEFAULT_TERMS);
    }

    // ── Parameter names ───────────────────────────────────────────────────────

    public function testTermsParamName(): void
    {
        $this->assertSame('terms', Search::TERMS_PARAM);
    }

    public function testSectionFilterName(): void
    {
        $this->assertSame('section_id', Search::SECTION_FILTER);
    }

    public function testAuthorFullNameFilterName(): void
    {
        $this->assertSame('author_fullname', Search::AUTHOR_FULL_NAME_FILTER);
    }

    public function testDocTypeFilterName(): void
    {
        $this->assertSame('type', Search::DOC_TYPE_FILTER);
    }

    public function testPublicationDateYearFilterName(): void
    {
        $this->assertSame('year', Search::PUBLICATION_DATE_YEAR_FILTER);
    }

    public function testVolumeFilterName(): void
    {
        $this->assertSame('volume_id', Search::VOLUME_FILTER);
    }

    // ── Array parameter variants ──────────────────────────────────────────────

    public function testArraySectionFilter(): void
    {
        $this->assertSame('section_id[]', Search::ARRAY_SECTION_FILTER);
    }

    public function testArrayAuthorFullNameFilter(): void
    {
        $this->assertSame('author_fullname[]', Search::ARRAY_AUTHOR_FULL_NAME_FILTER);
    }

    public function testArrayDocTypeFilter(): void
    {
        $this->assertSame('type[]', Search::ARRAY_DOC_TYPE_FILTER);
    }

    public function testArrayPublicationDateYearFilter(): void
    {
        $this->assertSame('year[]', Search::ARRAY_PUBLICATION_DATE_YEAR_FILTER);
    }

    public function testArrayVolumeFilter(): void
    {
        $this->assertSame('volume_id[]', Search::ARRAY_VOLUME_FILTER);
    }

    // ── SEARCH_FILTERS_MAPPING ────────────────────────────────────────────────

    public function testSearchFiltersMappingContainsSectionId(): void
    {
        $this->assertArrayHasKey(Search::SECTION_FILTER, Search::SEARCH_FILTERS_MAPPING);
        $this->assertSame('section_id_i', Search::SEARCH_FILTERS_MAPPING[Search::SECTION_FILTER]);
    }

    public function testSearchFiltersMappingContainsVolumeId(): void
    {
        $this->assertArrayHasKey(Search::VOLUME_FILTER, Search::SEARCH_FILTERS_MAPPING);
        $this->assertSame('volume_id_i', Search::SEARCH_FILTERS_MAPPING[Search::VOLUME_FILTER]);
    }

    public function testSearchFiltersMappingContainsDocType(): void
    {
        $this->assertArrayHasKey(Search::DOC_TYPE_FILTER, Search::SEARCH_FILTERS_MAPPING);
        $this->assertSame('doc_type_fs', Search::SEARCH_FILTERS_MAPPING[Search::DOC_TYPE_FILTER]);
    }

    public function testSearchFiltersMappingContainsAuthorFullName(): void
    {
        $this->assertArrayHasKey(Search::AUTHOR_FULL_NAME_FILTER, Search::SEARCH_FILTERS_MAPPING);
        $this->assertSame('author_fullname_t', Search::SEARCH_FILTERS_MAPPING[Search::AUTHOR_FULL_NAME_FILTER]);
    }

    public function testSearchFiltersMappingContainsPublicationDateYear(): void
    {
        $this->assertArrayHasKey(Search::PUBLICATION_DATE_YEAR_FILTER, Search::SEARCH_FILTERS_MAPPING);
        $this->assertSame('publication_date_year_fs', Search::SEARCH_FILTERS_MAPPING[Search::PUBLICATION_DATE_YEAR_FILTER]);
    }

    public function testSearchFiltersMappingHasFiveEntries(): void
    {
        $this->assertCount(5, Search::SEARCH_FILTERS_MAPPING);
    }

    // ── Facet name constants ──────────────────────────────────────────────────

    public function testVolumeFacetName(): void
    {
        $this->assertSame('volume', Search::VOLUME_FACET_NAME);
    }

    public function testSectionFacetName(): void
    {
        $this->assertSame('section', Search::SECTION_FACET_NAME);
    }

    public function testAuthorFacetName(): void
    {
        $this->assertSame('author', Search::AUTHOR_FACET_NAME);
    }

    // ── Array filter naming convention consistency ─────────────────────────────

    public function testArrayFiltersAreScalarFilterPlusBrackets(): void
    {
        $this->assertSame(Search::SECTION_FILTER . '[]', Search::ARRAY_SECTION_FILTER);
        $this->assertSame(Search::VOLUME_FILTER . '[]', Search::ARRAY_VOLUME_FILTER);
        $this->assertSame(Search::DOC_TYPE_FILTER . '[]', Search::ARRAY_DOC_TYPE_FILTER);
        $this->assertSame(Search::AUTHOR_FULL_NAME_FILTER . '[]', Search::ARRAY_AUTHOR_FULL_NAME_FILTER);
        $this->assertSame(Search::PUBLICATION_DATE_YEAR_FILTER . '[]', Search::ARRAY_PUBLICATION_DATE_YEAR_FILTER);
    }
}
