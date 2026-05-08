<?php

namespace App\Tests\Unit\Controller;

use App\Controller\ExportController;
use App\Entity\Paper;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Service\Export;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportControllerTest extends TestCase
{
    private ExportController $controller;
    private \PHPUnit\Framework\MockObject\MockObject $exportService;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $reviewRepository;
    private \PHPUnit\Framework\MockObject\MockObject $papersRepository;
    private \PHPUnit\Framework\MockObject\MockObject $journal;

    protected function setUp(): void
    {
        $this->exportService = $this->createMock(Export::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->controller = new ExportController($this->exportService, $this->entityManager);
        
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->papersRepository = $this->createMock(PapersRepository::class);
        $this->journal = $this->createMock(Review::class);
    }

    private function makeRequest(int $docId = 0, string $format = '', string $code = ''): Request
    {
        $query = $code !== '' && $code !== '0' ? ['code' => $code] : [];
        $request = Request::create('/api/export', \Symfony\Component\HttpFoundation\Request::METHOD_GET, $query);
        $request->attributes->set('docid', $docId);
        $request->attributes->set('format', $format);
        return $request;
    }

    public function testThrowsWhenDocIdIsZero(): void
    {
        $request = $this->makeRequest(0, Export::CSL_FORMAT);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('{docid}');

        $this->controller->__invoke($request);
    }

    public function testThrowsWhenFormatIsEmpty(): void
    {
        $request = $this->makeRequest(42, '');

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('{format}');

        $this->controller->__invoke($request);
    }

    public function testThrowsWhenFormatIsInvalid(): void
    {
        $request = $this->makeRequest(42, 'invalid_format');

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('invalid_format');

        $this->controller->__invoke($request);
    }

    public function testThrowsWhenCodeProvidedButJournalNotFound(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT, 'unknown-journal');

        $this->entityManager->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepository);

        $this->reviewRepository->method('getJournalByIdentifier')
            ->with('unknown-journal')
            ->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('unknown-journal');

        $this->controller->__invoke($request);
    }

    public function testCallsSolrServiceForNonJsonFormat(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT);

        $this->exportService->expects($this->once())
            ->method('setJournal')
            ->with(null)
            ->willReturn($this->exportService);

        $this->exportService->expects($this->once())
            ->method('getSolrCSLByFormat')
            ->with(42, Export::CSL_FORMAT)
            ->willReturn('{"title":"test"}');

        $response = $this->controller->__invoke($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(Export::HEADERS_FORMATS[Export::CSL_FORMAT], $response->headers->get('Content-Type'));
        $this->assertEquals('{"title":"test"}', $response->getContent());
    }

    public function testCallsPaperRepositoryForJsonFormat(): void
    {
        $request = $this->makeRequest(42, Export::JSON_FORMAT);

        $this->exportService->expects($this->once())
            ->method('setJournal')
            ->with(null)
            ->willReturn($this->exportService);

        $this->exportService->expects($this->never())->method('getSolrCSLByFormat');

        $this->entityManager->method('getRepository')
            ->with(Paper::class)
            ->willReturn($this->papersRepository);

        $this->papersRepository->expects($this->once())
            ->method('paperToJson')
            ->with(42, null)
            ->willReturn('{"id":42}');

        $response = $this->controller->__invoke($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(Export::HEADERS_FORMATS[Export::JSON_FORMAT], $response->headers->get('Content-Type'));
    }

    public function testLoadsJournalWhenCodeIsProvided(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT, 'myjournal');

        $this->journal->method('getRvid')->willReturn(7);

        $this->entityManager->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepository);

        $this->reviewRepository->method('getJournalByIdentifier')
            ->with('myjournal')
            ->willReturn($this->journal);

        $this->exportService->expects($this->once())
            ->method('setJournal')
            ->with($this->journal)
            ->willReturn($this->exportService);

        $this->exportService->method('getSolrCSLByFormat')
            ->with(42, Export::CSL_FORMAT)
            ->willReturn('<csl>data</csl>');

        $response = $this->controller->__invoke($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testPassesRvIdToJsonExportWhenJournalProvided(): void
    {
        $request = $this->makeRequest(42, Export::JSON_FORMAT, 'myjournal');

        $this->journal->method('getRvid')->willReturn(7);

        $this->entityManager->method('getRepository')->willReturnCallback(
            fn($class): \App\Repository\ReviewRepository|\PHPUnit\Framework\MockObject\MockObject|\App\Repository\PapersRepository|null => match ($class) {
                Review::class => $this->reviewRepository,
                Paper::class  => $this->papersRepository,
                default       => null,
            }
        );

        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);
        $this->exportService->method('setJournal')->willReturn($this->exportService);

        $this->papersRepository->expects($this->once())
            ->method('paperToJson')
            ->with(42, 7)
            ->willReturn('{"id":42}');

        $this->controller->__invoke($request);
    }

    public function testThrowsWhenExportResultIsNull(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT);

        $this->exportService->method('setJournal')->willReturn($this->exportService);
        $this->exportService->method('getSolrCSLByFormat')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('42');

        $this->controller->__invoke($request);
    }

    public function testThrowsWhenExportResultIsEmptyString(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT);

        $this->exportService->method('setJournal')->willReturn($this->exportService);
        $this->exportService->method('getSolrCSLByFormat')->willReturn('');

        $this->expectException(ResourceNotFoundException::class);

        $this->controller->__invoke($request);
    }

    public function testErrorMessageIncludesJournalCodeWhenSet(): void
    {
        $request = $this->makeRequest(42, Export::CSL_FORMAT, 'myjournal');

        $this->journal->method('getRvid')->willReturn(7);

        $this->entityManager->method('getRepository')->willReturn($this->reviewRepository);
        $this->reviewRepository->method('getJournalByIdentifier')->willReturn($this->journal);

        $this->exportService->method('setJournal')->willReturn($this->exportService);
        $this->exportService->method('getSolrCSLByFormat')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('myjournal');

        $this->controller->__invoke($request);
    }

    /**
     * @dataProvider availableFormatsDataProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('availableFormatsDataProvider')]
    public function testAllAvailableFormatsAreAccepted(string $format): void
    {
        $request = $this->makeRequest(42, $format);

        $this->exportService->method('setJournal')->willReturn($this->exportService);

        if ($format === Export::JSON_FORMAT) {
            $this->entityManager->method('getRepository')->willReturn($this->papersRepository);
            $this->papersRepository->method('paperToJson')->willReturn('{}');
        } else {
            $this->exportService->method('getSolrCSLByFormat')->willReturn('data');
        }

        $response = $this->controller->__invoke($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public static function availableFormatsDataProvider(): array
    {
        return array_map(
            static fn(string $fmt): array => [$fmt],
            Export::AVAILABLE_FORMATS
        );
    }

    /**
     * Bug fix verification: $request->get('code') was deprecated in Symfony 7.4.
     * After fix it uses $request->query->get('code', '').
     * Passing code via route attributes must NOT load a journal.
     */
    public function testCodeFromRouteAttributeIsNotUsedAsJournalFilter(): void
    {
        $request = Request::create('/api/export', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('docid', 42);
        $request->attributes->set('format', Export::CSL_FORMAT);
        $request->attributes->set('code', 'some-journal'); // only in attributes, not query

        // After the fix: query->get('code', '') returns '' → no journal lookup
        $this->reviewRepository->expects($this->never())->method('getJournalByIdentifier');

        $this->exportService->method('setJournal')->with(null)->willReturn($this->exportService);
        $this->exportService->method('getSolrCSLByFormat')->willReturn('data');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}