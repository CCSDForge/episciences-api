<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\FeedController;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use App\Service\Solr\SolrFeedService;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Feed\Writer\Feed;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FeedControllerTest extends TestCase
{
    private FeedController $controller;
    private MockObject|SolrFeedService $feedService;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|ReviewRepository $reviewRepository;
    private MockObject|Feed $feed;

    protected function setUp(): void
    {
        $this->controller = new FeedController();
        $this->feedService = $this->createMock(SolrFeedService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->feed = $this->createMock(Feed::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Review::class)
            ->willReturn($this->reviewRepository);
    }

    public function testInvokeWithRssPath(): void
    {
        $request = Request::create('/api/feed/rss/testjournal', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('code', 'testjournal');

        $this->reviewRepository
            ->method('getJournalByIdentifier')
            ->with('testjournal')
            ->willReturn($this->createStub(\App\Entity\Review::class));

        $this->feedService
            ->method('setJournal')
            ->with($this->createStub(\App\Entity\Review::class))
            ->willReturn($this->feedService);

        $this->feedService
            ->expects($this->once())
            ->method('getSolrFeed')
            ->with('rss')
            ->willReturn($this->feed);

        $this->feed
            ->expects($this->once())
            ->method('export')
            ->with('rss')
            ->willReturn('<rss>content</rss>');

        $response = $this->controller->__invoke($request, $this->feedService, $this->entityManager);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertSame('<rss>content</rss>', $response->getContent());
    }

    public function testInvokeWithAtomPath(): void
    {
        $request = Request::create('/api/feed/atom/testjournal', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('code', 'testjournal');

        $this->reviewRepository
            ->method('getJournalByIdentifier')
            ->with('testjournal')
            ->willReturn($this->createStub(\App\Entity\Review::class));

        $this->feedService
            ->method('setJournal')
            ->with($this->createStub(\App\Entity\Review::class))
            ->willReturn($this->feedService);

        $this->feedService
            ->expects($this->once())
            ->method('getSolrFeed')
            ->with('atom')
            ->willReturn($this->feed);

        $this->feed
            ->expects($this->once())
            ->method('export')
            ->with('atom')
            ->willReturn('<feed>content</feed>');

        $response = $this->controller->__invoke($request, $this->feedService, $this->entityManager);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertSame('<feed>content</feed>', $response->getContent());
    }

    public function testInvokeThrowsExceptionWhenJournalNotFound(): void
    {
        $request = Request::create('/api/feed/rss/nonexistent', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('code', 'nonexistent');

        $this->reviewRepository
            ->method('getJournalByIdentifier')
            ->with('nonexistent')
            ->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Oops! Feed cannot be generated: not found Journal nonexistent');

        $this->controller->__invoke($request, $this->feedService, $this->entityManager);
    }

    public function testFormatDetectionWithAtomInPath(): void
    {
        $request = Request::create('/api/feed/atom/myjournal', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('code', 'myjournal');

        $this->reviewRepository
            ->method('getJournalByIdentifier')
            ->with('myjournal')
            ->willReturn($this->createStub(\App\Entity\Review::class));

        $this->feedService
            ->method('setJournal')
            ->willReturn($this->feedService);

        $this->feedService
            ->method('getSolrFeed')
            ->with('atom')
            ->willReturn($this->feed);

        $this->feed->method('export')->willReturn('');

        $this->controller->__invoke($request, $this->feedService, $this->entityManager);
    }

    public function testFormatDetectionDefaultsToRss(): void
    {
        $request = Request::create('/api/feed/rss/myjournal', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->attributes->set('code', 'myjournal');

        $this->reviewRepository
            ->method('getJournalByIdentifier')
            ->with('myjournal')
            ->willReturn($this->createStub(\App\Entity\Review::class));

        $this->feedService
            ->method('setJournal')
            ->willReturn($this->feedService);

        $this->feedService
            ->method('getSolrFeed')
            ->with('rss')
            ->willReturn($this->feed);

        $this->feed->method('export')->willReturn('');

        $this->controller->__invoke($request, $this->feedService, $this->entityManager);
    }
}
