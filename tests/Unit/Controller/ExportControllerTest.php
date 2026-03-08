<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ExportController;
use App\Entity\Paper;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Service\Export;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExportControllerTest extends TestCase
{
    private ExportController $controller;

    protected function setUp(): void
    {
        $this->controller = new ExportController();
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function makeRequest(int $docId, string $format, string $code = ''): Request
    {
        $query = $code !== '' && $code !== '0' ? ['code' => $code] : [];
        $request = Request::create('/api/export/' . $format . '/' . $docId, \Symfony\Component\HttpFoundation\Request::METHOD_GET, $query);
        $request->attributes->set('docid', (string)$docId);
        $request->attributes->set('format', $format);
        return $request;
    }

    private function makeExportMock(): Export
    {
        return $this->getMockBuilder(Export::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setJournal', 'getSolrCSLByFormat'])
            ->getMock();
    }

    private function makeEmWithRepos(
        ?Review $journal,
        ?string $paperJson = null,
    ): EntityManagerInterface {
        $em = $this->createMock(EntityManagerInterface::class);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->willReturn($journal);

        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('paperToJson')->willReturn($paperJson);

        $em->method('getRepository')->willReturnMap([
            [Review::class, $reviewRepo],
            [Paper::class, $paperRepo],
        ]);

        return $em;
    }

    // ── missing docId → exception ─────────────────────────────────────────────

    public function testZeroDocIdThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/docid/i');

        $request = Request::create('/api/export/csl/0', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('docid', '0');
        $request->attributes->set('format', 'csl');

        ($this->controller)($request, $this->makeExportMock(), $this->createMock(EntityManagerInterface::class));
    }

    // ── missing format → exception ─────────────────────────────────────────────

    public function testEmptyFormatThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/format/i');

        $request = Request::create('/api/export//42', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('docid', '42');
        $request->attributes->set('format', '');

        ($this->controller)($request, $this->makeExportMock(), $this->createMock(EntityManagerInterface::class));
    }

    // ── invalid format → exception with format name ───────────────────────────

    public function testInvalidFormatThrowsResourceNotFoundExceptionWithFormatName(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/badformat/');

        ($this->controller)(
            $this->makeRequest(42, 'badformat'),
            $this->makeExportMock(),
            $this->createMock(EntityManagerInterface::class)
        );
    }

    public function testInvalidFormatMessageContainsAvailableFormats(): void
    {
        try {
            ($this->controller)(
                $this->makeRequest(42, 'badformat'),
                $this->makeExportMock(),
                $this->createMock(EntityManagerInterface::class)
            );
            $this->fail('Expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertStringContainsString('csl', $e->getMessage());
        }
    }

    // ── valid format but journal not found ────────────────────────────────────

    public function testJournalNotFoundThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/unknownjournal/');

        $em = $this->makeEmWithRepos(null);

        ($this->controller)(
            $this->makeRequest(42, Export::CSL_FORMAT, 'unknownjournal'),
            $this->makeExportMock(),
            $em
        );
    }

    // ── CSL format, no journal code ───────────────────────────────────────────

    public function testCslFormatWithoutJournalCallsSolr(): void
    {
        $exportService = $this->makeExportMock();
        $exportService->expects($this->once())
            ->method('setJournal')
            ->with(null);
        $exportService->expects($this->once())
            ->method('getSolrCSLByFormat')
            ->with(42, Export::CSL_FORMAT)
            ->willReturn('{"title":"Test"}');

        $em = $this->makeEmWithRepos(null);

        $response = ($this->controller)(
            $this->makeRequest(42, Export::CSL_FORMAT),
            $exportService,
            $em
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertSame('{"title":"Test"}', $response->getContent());
    }

    // ── JSON format → uses paperToJson, not solr ──────────────────────────────

    public function testJsonFormatCallsPaperToJson(): void
    {
        $exportService = $this->makeExportMock();
        $exportService->expects($this->never())->method('getSolrCSLByFormat');
        $exportService->method('setJournal');

        $em = $this->makeEmWithRepos(null, '{"docid":42}');

        $response = ($this->controller)(
            $this->makeRequest(42, Export::JSON_FORMAT),
            $exportService,
            $em
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('{"docid":42}', $response->getContent());
    }

    // ── null export → exception ────────────────────────────────────────────────

    public function testNullExportThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $exportService = $this->makeExportMock();
        $exportService->method('setJournal');
        $exportService->method('getSolrCSLByFormat')->willReturn(null);

        $em = $this->makeEmWithRepos(null);

        ($this->controller)(
            $this->makeRequest(42, Export::CSL_FORMAT),
            $exportService,
            $em
        );
    }

    public function testNullExportMessageContainsDocId(): void
    {
        try {
            $exportService = $this->makeExportMock();
            $exportService->method('setJournal');
            $exportService->method('getSolrCSLByFormat')->willReturn(null);

            ($this->controller)(
                $this->makeRequest(99, Export::CSL_FORMAT),
                $exportService,
                $this->makeEmWithRepos(null)
            );
            $this->fail('Expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertStringContainsString('99', $e->getMessage());
        }
    }

    // ── with journal code → journal passed to service ──────────────────────────

    public function testWithJournalCodePassesJournalToExportService(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $exportService = $this->makeExportMock();
        $exportService->expects($this->once())->method('setJournal')->with($journal);
        $exportService->method('getSolrCSLByFormat')->willReturn('<xml/>');

        $em = $this->makeEmWithRepos($journal);

        $response = ($this->controller)(
            $this->makeRequest(42, Export::CSL_FORMAT, 'epijinfo'),
            $exportService,
            $em
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    // ── bibtex format → text/plain header ─────────────────────────────────────

    public function testBibtexFormatReturnsPlaintextContentType(): void
    {
        $exportService = $this->makeExportMock();
        $exportService->method('setJournal');
        $exportService->method('getSolrCSLByFormat')->willReturn('@article{test}');

        $response = ($this->controller)(
            $this->makeRequest(42, Export::BIBTEX_FORMAT),
            $exportService,
            $this->makeEmWithRepos(null)
        );

        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    // ── all valid formats are accepted ────────────────────────────────────────
    #[\PHPUnit\Framework\Attributes\DataProvider('validFormatProvider')]
    public function testAllValidFormatsAreAccepted(string $format): void
    {
        $exportService = $this->makeExportMock();
        $exportService->method('setJournal');
        $exportService->method('getSolrCSLByFormat')->willReturn('<data/>');

        $em = $this->makeEmWithRepos(null, '{}');

        // Should not throw ResourceNotFoundException for a known format
        $response = ($this->controller)(
            $this->makeRequest(1, $format),
            $exportService,
            $em
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public static function validFormatProvider(): array
    {
        return array_combine(
            Export::AVAILABLE_FORMATS,
            array_map(static fn(string $f) => [$f], Export::AVAILABLE_FORMATS)
        );
    }
}
