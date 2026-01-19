<?php

namespace App\Tests\Resource;

use App\Entity\Review;
use App\Resource\Rss;
use Laminas\Feed\Writer\Feed as FeedWriter;
use PHPUnit\Framework\TestCase;

class RssTest extends TestCase
{
    public function testGetFeedDefaultPortal(): void
    {
        $rss = new Rss();
        $feed = $rss->getFeed();

        $this->assertInstanceOf(FeedWriter::class, $feed);

        // Checks for the portal (default values when $review is null)
        $this->assertEquals('Episciences', $feed->getTitle());
        $this->assertEquals('https://portal.episciences.org', $feed->getLink());
        $this->assertEquals('Episciences: latest publications', $feed->getDescription());

        // Check feed link - uses applicationUrl as default baseUrl
        $expectedFeedLink = 'https://portal.episciences.org/api/feed/rss/portal';

        $links = $feed->getFeedLinks();
        $this->assertArrayHasKey('rss', $links);
        $this->assertEquals($expectedFeedLink, $links['rss']);
    }

    public function testGetFeedWithReview(): void
    {
        // Create Mock for Review entity
        $reviewMock = $this->createMock(Review::class);
        $reviewMock->method('getCode')->willReturn('myjournal');
        $reviewMock->method('getName')->willReturn('My Journal Name');

        $rss = new Rss();
        $rss->setReview($reviewMock);

        $feed = $rss->getFeed();

        // Checks based on the mock
        $this->assertEquals('My Journal Name', $feed->getTitle());
        $this->assertEquals('https://myjournal.episciences.org', $feed->getLink());

        // Check image
        $image = $feed->getImage();
        $this->assertEquals('https://myjournal.episciences.org/logos/logo-myjournal-small.svg', $image['uri']);

        // Check feed link - uses applicationUrl as default baseUrl
        $expectedFeedLink = 'https://myjournal.episciences.org/api/feed/rss/myjournal';
        $links = $feed->getFeedLinks();
        $this->assertEquals($expectedFeedLink, $links['rss']);
    }

    public function testSetFeedType(): void
    {
        $rss = new Rss();
        $rss->setFeedType('atom');

        $this->assertEquals('atom', $rss->getFeedType());

        $feed = $rss->getFeed();

        // The feed link must now contain 'atom' instead of 'rss'
        // and the key in the links array must be 'atom'
        $expectedFeedLink = 'https://portal.episciences.org/api/feed/atom/portal';
        $links = $feed->getFeedLinks();

        $this->assertArrayHasKey('atom', $links);
        $this->assertEquals($expectedFeedLink, $links['atom']);
    }

    public function testGetSetReview(): void
    {
        $rss = new Rss();
        $this->assertNull($rss->getReview());

        $reviewMock = $this->createMock(Review::class);
        $rss->setReview($reviewMock);

        $this->assertSame($reviewMock, $rss->getReview());
    }
}