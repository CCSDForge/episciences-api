<?php

namespace App\Tests\Unit\Resource;

use App\Entity\Review;
use App\Resource\Rss;
use PHPUnit\Framework\TestCase;

class RssTest extends TestCase
{
    public function testSetFeedTypeReturnsSelf(): void
    {
        $rss = new Rss();

        $result = $rss->setFeedType('atom');

        $this->assertSame($rss, $result);
    }

    public function testGetFeedTypeDefaultsToRss(): void
    {
        $rss = new Rss();

        $this->assertEquals('rss', $rss->getFeedType());
    }

    public function testSetFeedTypeChangesValue(): void
    {
        $rss = new Rss();

        $rss->setFeedType('atom');

        $this->assertEquals('atom', $rss->getFeedType());
    }

    public function testGetFeedWithRssTypeContainsRssInFeedLink(): void
    {
        $mockReview = $this->createMock(Review::class);
        $mockReview->method('getCode')->willReturn('testjournal');
        $mockReview->method('getName')->willReturn('Test Journal');

        $rss = new Rss();
        $rss->setReview($mockReview);
        $rss->setFeedType('rss');

        $feed = $rss->getFeed();
        $feedLinks = $feed->getFeedLinks();

        $this->assertArrayHasKey('rss', $feedLinks);
        $this->assertStringContainsString('/rss/', $feedLinks['rss']);
    }

    public function testGetFeedWithAtomTypeContainsAtomInFeedLink(): void
    {
        $mockReview = $this->createMock(Review::class);
        $mockReview->method('getCode')->willReturn('testjournal');
        $mockReview->method('getName')->willReturn('Test Journal');

        $rss = new Rss();
        $rss->setReview($mockReview);
        $rss->setFeedType('atom');

        $feed = $rss->getFeed();
        $feedLinks = $feed->getFeedLinks();

        $this->assertArrayHasKey('atom', $feedLinks);
        $this->assertStringContainsString('/atom/', $feedLinks['atom']);
    }

    public function testFluentInterface(): void
    {
        $mockReview = $this->createMock(Review::class);
        $mockReview->method('getCode')->willReturn('testjournal');
        $mockReview->method('getName')->willReturn('Test Journal');

        $rss = new Rss();

        $feed = $rss
            ->setReview($mockReview)
            ->setBaseUrl('https://api.episciences.org')
            ->setFeedType('atom')
            ->getFeed();

        $this->assertStringContainsString('/atom/', $feed->getFeedLinks()['atom']);
        $this->assertStringContainsString('https://api.episciences.org', $feed->getFeedLinks()['atom']);
    }

    public function testSetBaseUrlAffectsFeedLink(): void
    {
        $mockReview = $this->createMock(Review::class);
        $mockReview->method('getCode')->willReturn('testjournal');
        $mockReview->method('getName')->willReturn('Test Journal');

        $rss = new Rss();
        $rss->setReview($mockReview);
        $rss->setBaseUrl('https://custom.example.org');
        $rss->setFeedType('rss');

        $feed = $rss->getFeed();
        $feedLinks = $feed->getFeedLinks();

        $this->assertStringContainsString('https://custom.example.org', $feedLinks['rss']);
    }
}
