<?php

namespace App\Tests\Unit\Controller;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Controller\IndexingDatabaseController;
use App\Entity\IndexingDatabase;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\IndexingDatabaseRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class IndexingDatabaseControllerTest extends TestCase
{
    private IndexingDatabaseController $controller;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $reviewRepository;
    private \PHPUnit\Framework\MockObject\MockObject $indexingDatabaseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $journal;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->controller = new IndexingDatabaseController($this->entityManager);

        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->indexingDatabaseRepository = $this->createMock(IndexingDatabaseRepository::class);
        $this->journal = $this->createMock(Review::class);
    }

    private function stubRepositories(string $code): void
    {
        $this->entityManager->method('getRepository')->willReturnMap([
            [Review::class, $this->reviewRepository],
            [IndexingDatabase::class, $this->indexingDatabaseRepository],
        ]);
        $this->reviewRepository->method('getJournalByIdentifier')->with($code)->willReturn($this->journal);
        $this->journal->method('getRvid')->willReturn(3);
    }

    /**
     * @return list<IndexingDatabase>
     */
    private function sampleDatabases(): array
    {
        $databases = [];
        foreach (['Scopus', 'Web of Science', 'Google Scholar', 'OpenAIRE'] as $name) {
            $db = $this->createMock(IndexingDatabase::class);
            $db->method('getName')->willReturn($name);
            $databases[] = $db;
        }
        return $databases;
    }

    public function testReturnsEmptyPaginatorWhenRequestIsNull(): void
    {
        $result = $this->controller->__invoke(null);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(0, $result);
    }

    public function testThrowsResourceNotFoundExceptionWhenJournalNotFound(): void
    {
        $request = Request::create('/api/journals/indexing-database/unknown', Request::METHOD_GET);
        $request->attributes->set('code', 'unknown');

        $this->entityManager->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepository);
        $this->reviewRepository->method('getJournalByIdentifier')
            ->with('unknown')
            ->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('unknown');

        $this->controller->__invoke($request);
    }

    public function testReturnsAllElementsByDefault(): void
    {
        $request = Request::create('/api/journals/indexing-database/myjournal', Request::METHOD_GET);
        $request->attributes->set('code', 'myjournal');

        $this->stubRepositories('myjournal');
        $this->indexingDatabaseRepository->method('findByReviewId')->with(3)->willReturn($this->sampleDatabases());

        $result = $this->controller->__invoke($request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(4, $result);
    }

    public function testAppliesPaginationWhenEnabled(): void
    {
        $request = Request::create('/api/journals/indexing-database/myjournal', Request::METHOD_GET, [
            'pagination' => '1',
            'page' => '2',
            'itemsPerPage' => '2',
        ]);
        $request->attributes->set('code', 'myjournal');

        $this->stubRepositories('myjournal');
        $this->indexingDatabaseRepository->method('findByReviewId')->with(3)->willReturn($this->sampleDatabases());

        // page=2, itemsPerPage=2 → firstResult=2, returns the 3rd and 4th entries
        $result = $this->controller->__invoke($request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame('Google Scholar', array_values(iterator_to_array($result))[0]->getName());
    }

    public function testPaginationDisabledReturnsFullCollectionIgnoringPage(): void
    {
        // pagination=false → the whole collection is returned, page and itemsPerPage are ignored.
        // The RequestedPageSubscriber ignores the page in this case, so the controller
        // must not slice the collection either (see docs/pagination-requested-page.md).
        $request = Request::create('/api/journals/indexing-database/myjournal', Request::METHOD_GET, [
            'pagination' => 'false',
            'page' => '100',
            'itemsPerPage' => '2', // fewer than the collection: ignored as well
        ]);
        $request->attributes->set('code', 'myjournal');

        $this->stubRepositories('myjournal');
        $this->indexingDatabaseRepository->method('findByReviewId')->with(3)->willReturn($this->sampleDatabases());

        $result = $this->controller->__invoke($request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(4, $result);
    }

    public function testPaginationDisabledWithNullEquivalentStillPaginates(): void
    {
        // The 'pagination' param set to '0' disables pagination as well.
        $request = Request::create('/api/journals/indexing-database/myjournal', Request::METHOD_GET, [
            'pagination' => '0',
        ]);
        $request->attributes->set('code', 'myjournal');

        $this->stubRepositories('myjournal');
        $this->indexingDatabaseRepository->method('findByReviewId')->with(3)->willReturn($this->sampleDatabases());

        $result = $this->controller->__invoke($request);

        $this->assertInstanceOf(ArrayPaginator::class, $result);
        $this->assertCount(4, $result);
    }
}
