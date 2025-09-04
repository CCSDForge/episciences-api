<?php

namespace App\Tests\Unit\Service;

use App\Service\Export;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExportServiceTest extends TestCase
{
    private Export $exportService;
    private ParameterBagInterface $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Mock the getJournal method to return null (no database dependency)
        $this->exportService = $this->getMockBuilder(Export::class)
            ->setConstructorArgs([$httpClient, $logger, $this->parameterBag])
            ->onlyMethods(['getJournal'])
            ->getMock();
        
        $this->exportService->method('getJournal')->willReturn(null);
    }

    public function testExportToFormatQueryBasicStructure(): void
    {
        $this->parameterBag
            ->expects($this->once())
            ->method('get')
            ->with('app.solr.host')
            ->willReturn('http://localhost:8983/solr/episciences');

        $docId = 12345;
        $format = Export::CSL_FORMAT;

        $query = $this->exportService->exportToFormatQuery($docId, $format);

        $this->assertStringContainsString('http://localhost:8983/solr/episciences/select/?', $query);
        $this->assertStringContainsString('fl=doc_csl', $query);
        $this->assertStringContainsString('q=docid:12345 paperid:12345', $query);
        $this->assertStringContainsString('indent=true&q.op=OR', $query);
    }

    public function testExportToFormatQueryWithDifferentFormats(): void
    {
        $this->parameterBag->method('get')->willReturn('http://localhost:8983/solr/core');

        $docId = 67890;

        // Test different export formats
        $formats = [
            Export::BIBTEX_FORMAT => 'doc_bibtex',
            Export::TEI_FORMAT => 'doc_tei',
            Export::JSON_FORMAT => 'doc_json',
            Export::DC_FORMAT => 'doc_dc',
        ];

        foreach ($formats as $format => $expectedField) {
            $query = $this->exportService->exportToFormatQuery($docId, $format);
            
            $this->assertStringContainsString("fl=$expectedField", $query);
            $this->assertStringContainsString('q=docid:67890 paperid:67890', $query);
        }
    }

    public function testExportToFormatQueryWithEmptyFormat(): void
    {
        $this->parameterBag->method('get')->willReturn('http://localhost:8983/solr/core');

        $docId = 123;
        $format = '';

        $query = $this->exportService->exportToFormatQuery($docId, $format);

        // Should not contain fl parameter when format is empty
        $this->assertStringNotContainsString('fl=', $query);
        $this->assertStringContainsString('q=docid:123 paperid:123', $query);
    }

    public function testExportToFormatQueryWithZeroDocId(): void
    {
        $this->parameterBag->method('get')->willReturn('http://localhost:8983/solr/core');

        $docId = 0;
        $format = Export::CSL_FORMAT;

        $query = $this->exportService->exportToFormatQuery($docId, $format);

        // Should not contain docid query when docId is 0
        $this->assertStringNotContainsString('q=docid:', $query);
        $this->assertStringNotContainsString('paperid:', $query);
        $this->assertStringContainsString('fl=doc_csl', $query);
    }

    public function testExportToFormatQueryDefaultFormat(): void
    {
        $this->parameterBag->method('get')->willReturn('http://localhost:8983/solr/core');

        $docId = 456;
        
        // Test with default format parameter (should be CSL)
        $query = $this->exportService->exportToFormatQuery($docId, Export::CSL_FORMAT);

        // Default format should be CSL
        $this->assertStringContainsString('fl=doc_csl', $query);
    }

    public function testAvailableFormatsConstant(): void
    {
        $expectedFormats = [
            'tei', 'dc', 'crossref', 'zbjats', 'doaj', 'bibtex', 'csl', 'openaire', 'json'
        ];

        $this->assertEquals($expectedFormats, Export::AVAILABLE_FORMATS);
    }

    public function testHeadersFormatsConstant(): void
    {
        $expectedHeaders = [
            'csl' => 'application/json',
            'bibtex' => 'text/plain',
            'openaire' => 'text/xml',
            'crossref' => 'text/xml',
            'doaj' => 'text/xml',
            'zbjats' => 'text/xml',
            'tei' => 'text/xml',
            'dc' => 'text/xml',
            'json' => 'application/json'
        ];

        $this->assertEquals($expectedHeaders, Export::HEADERS_FORMATS);
    }

    public function testSolrCslPrefix(): void
    {
        $this->assertEquals('doc_', Export::SOLR_CSL_PREFIX);
    }

    public function testFormatConstants(): void
    {
        $this->assertEquals('tei', Export::TEI_FORMAT);
        $this->assertEquals('dc', Export::DC_FORMAT);
        $this->assertEquals('crossref', Export::CROSSREF_FORMAT);
        $this->assertEquals('zbjats', Export::ZBJATS_FORMAT);
        $this->assertEquals('doaj', Export::DOAJ_FORMAT);
        $this->assertEquals('bibtex', Export::BIBTEX_FORMAT);
        $this->assertEquals('csl', Export::CSL_FORMAT);
        $this->assertEquals('openaire', Export::OPENAIRE_FORMAT);
        $this->assertEquals('json', Export::JSON_FORMAT);
    }

    public function testExportToFormatQueryUrlEncoding(): void
    {
        $this->parameterBag->method('get')->willReturn('http://localhost:8983/solr/special core');

        $docId = 789;
        $format = Export::CSL_FORMAT;

        $query = $this->exportService->exportToFormatQuery($docId, $format);

        // The URL should be properly constructed even with spaces in the host
        $this->assertStringContainsString('http://localhost:8983/solr/special core/select/?', $query);
    }
}