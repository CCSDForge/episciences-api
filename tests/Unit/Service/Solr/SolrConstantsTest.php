<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Solr;

use App\AppConstants;
use App\Service\Solr\SolrConstants;
use PHPUnit\Framework\TestCase;

final class SolrConstantsTest extends TestCase
{
    public function testSolrMaxReturnedFacetsResultsMatchesAppConstants(): void
    {
        $this->assertSame(
            AppConstants::MAXIMUM_ITEMS_PER_PAGE,
            SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS
        );
    }

    public function testSolrFacetSeparator(): void
    {
        $this->assertSame('_FacetSep_', SolrConstants::SOLR_FACET_SEPARATOR);
    }

    public function testSolrOthersFacetSeparator(): void
    {
        $this->assertSame('Others_FacetSep_', SolrConstants::SOLR_OTHERS_FACET_SEPARATOR);
        $this->assertStringContainsString(
            SolrConstants::SOLR_OTHERS_PREFIX,
            SolrConstants::SOLR_OTHERS_FACET_SEPARATOR
        );
    }

    public function testSolrOthersPrefix(): void
    {
        $this->assertSame('Others', SolrConstants::SOLR_OTHERS_PREFIX);
    }

    public function testSolrAllPrefix(): void
    {
        $this->assertSame('All', SolrConstants::SOLR_ALL_PREFIX);
    }

    public function testSolrIndex(): void
    {
        $this->assertSame('index', SolrConstants::SOLR_INDEX);
    }

    public function testSolrFacetCount(): void
    {
        $this->assertSame('count', SolrConstants::SOLR_FACET_COUNT);
    }

    public function testSolrFacetName(): void
    {
        $this->assertSame('name', SolrConstants::SOLR_FACET_NAME);
    }

    public function testSolrLabel(): void
    {
        $this->assertSame('label', SolrConstants::SOLR_LABEL);
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
