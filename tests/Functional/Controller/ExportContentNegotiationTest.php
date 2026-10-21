<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Service\Export;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/papers/export/{docid}/{format} through the whole HTTP kernel: the media types of
 * the exported documents (text/plain, text/xml) are accepted by the content negotiation.
 *
 * Needs neither a database nor Solr: the Solr export service is replaced by a stub.
 */
class ExportContentNegotiationTest extends KernelTestCase
{
    private const string BIBTEX = '@article{1, title = {A title}}';
    private const string TEI = '<TEI/>';

    /**
     * @return array<string, array{string, string, string, string}> format, Accept header, expected body, expected Content-Type
     */
    public static function exportedDocumentProvider(): array
    {
        return [
            'bibtex as text/plain' => [Export::BIBTEX_FORMAT, 'text/plain', self::BIBTEX, 'text/plain'],
            'tei as text/xml' => [Export::TEI_FORMAT, 'text/xml', self::TEI, 'text/xml'],
            'tei as application/xml' => [Export::TEI_FORMAT, 'application/xml', self::TEI, 'text/xml'],
            'bibtex as */*' => [Export::BIBTEX_FORMAT, '*/*', self::BIBTEX, 'text/plain'],
        ];
    }

    #[DataProvider('exportedDocumentProvider')]
    public function testExportedDocumentIsReturnedForItsMediaType(string $format, string $accept, string $body, string $contentType): void
    {
        $this->stubSolrExport([Export::BIBTEX_FORMAT => self::BIBTEX, Export::TEI_FORMAT => self::TEI]);

        $response = $this->request("/api/papers/export/1/$format", $accept);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame($body, $response->getContent());
        $this->assertStringStartsWith($contentType, (string)$response->headers->get('Content-Type'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptProvider(): array
    {
        return [
            'text/plain' => ['text/plain'],
            'text/xml' => ['text/xml'],
            'application/ld+json' => ['application/ld+json'],
        ];
    }

    /**
     * An unknown format is a 404 whatever the Accept header, not a 406 nor a server error.
     */
    #[DataProvider('acceptProvider')]
    public function testUnknownFormatIsNotFound(string $accept): void
    {
        $this->stubSolrExport([]);

        $response = $this->request('/api/papers/export/1/unknown', $accept);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[DataProvider('acceptProvider')]
    public function testDocumentNotIndexedIsNotFound(string $accept): void
    {
        $this->stubSolrExport([]);

        $response = $this->request('/api/papers/export/1/' . Export::BIBTEX_FORMAT, $accept);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $documents exported document by format
     */
    private function stubSolrExport(array $documents): void
    {
        self::bootKernel();

        $export = $this->createStub(Export::class);
        $export->method('getSolrCSLByFormat')->willReturnCallback(
            static fn(int $docId, string $format): ?string => $documents[$format] ?? null
        );

        static::getContainer()->set(Export::class, $export);
    }

    private function request(string $uri, string $accept): Response
    {
        $request = Request::create($uri, server: ['HTTP_ACCEPT' => $accept, 'HTTPS' => 'on']);

        return self::$kernel->handle($request);
    }
}
