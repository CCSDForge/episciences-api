<?php

namespace App\Tests\Unit\Service\Solr;

use App\AppConstants;
use App\Service\Solr\SolrConstants;
use PHPUnit\Framework\TestCase;

class SolrConstantsTest extends TestCase
{
    public function testSolrMaxReturnedFacetsResultsMatchesAppConstants(): void
    {
        $this->assertEquals(
            AppConstants::MAXIMUM_ITEMS_PER_PAGE,
            SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS
        );
    }

    public function testSolrFacetSeparator(): void
    {
        $this->assertEquals('_FacetSep_', SolrConstants::SOLR_FACET_SEPARATOR);
    }

    public function testSolrOthersFacetSeparator(): void
    {
        $this->assertEquals('Others_FacetSep_', SolrConstants::SOLR_OTHERS_FACET_SEPARATOR);
        $this->assertStringContainsString(
            SolrConstants::SOLR_OTHERS_PREFIX,
            SolrConstants::SOLR_OTHERS_FACET_SEPARATOR
        );
    }

    public function testSolrOthersPrefix(): void
    {
        $this->assertEquals('Others', SolrConstants::SOLR_OTHERS_PREFIX);
    }

    public function testSolrAllPrefix(): void
    {
        $this->assertEquals('All', SolrConstants::SOLR_ALL_PREFIX);
    }

    public function testSolrIndex(): void
    {
        $this->assertEquals('index', SolrConstants::SOLR_INDEX);
    }

    public function testSolrFacetCount(): void
    {
        $this->assertEquals('count', SolrConstants::SOLR_FACET_COUNT);
    }

    public function testSolrFacetName(): void
    {
        $this->assertEquals('name', SolrConstants::SOLR_FACET_NAME);
    }

    public function testSolrLabel(): void
    {
        $this->assertEquals('label', SolrConstants::SOLR_LABEL);
    }

    public function testAllConstantsAreDefined(): void
    {
        $reflection = new \ReflectionClass(SolrConstants::class);
        $constants = $reflection->getConstants();

        $expectedConstants = [
            'SOLR_MAX_RETURNED_FACETS_RESULTS',
            'SOLR_FACET_SEPARATOR',
            'SOLR_OTHERS_FACET_SEPARATOR',
            'SOLR_OTHERS_PREFIX',
            'SOLR_ALL_PREFIX',
            'SOLR_INDEX',
            'SOLR_FACET_COUNT',
            'SOLR_FACET_NAME',
            'SOLR_LABEL',
        ];

        foreach ($expectedConstants as $constant) {
            $this->assertArrayHasKey($constant, $constants, "Constant $constant should exist");
        }

        $this->assertCount(count($expectedConstants), $constants);
    }
}
