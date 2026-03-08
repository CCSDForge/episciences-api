<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\News;
use App\Entity\Page;
use App\Entity\User;
use App\AppConstants;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for API entities News and Page.
 *
 * These entities are exposed via API Platform and have constants,
 * defaults, and getters/setters that should be guarded against regressions.
 */
final class ApiEntitiesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // News
    // ══════════════════════════════════════════════════════════════════════════

    public function testNewsTableConstant(): void
    {
        $this->assertSame('news', News::TABLE);
    }

    public function testNewsFiltersHasRvcodeKey(): void
    {
        $this->assertArrayHasKey('rvcode', News::FILTERS);
        $this->assertSame(AppConstants::FILTER_TYPE_EXACT, News::FILTERS['rvcode']);
    }

    public function testNewsFiltersHasNewsCodeKey(): void
    {
        $this->assertArrayHasKey('news_code', News::FILTERS);
    }

    public function testNewsDefaultTitleIsEmptyArray(): void
    {
        $news = new News();
        $this->assertSame([], $news->getTitle());
    }

    public function testNewsDefaultContentIsNull(): void
    {
        $news = new News();
        $this->assertNull($news->getContent());
    }

    public function testNewsDefaultLinkIsNull(): void
    {
        $news = new News();
        $this->assertNull($news->getLink());
    }

    public function testNewsDefaultDateCreationIsNull(): void
    {
        $news = new News();
        $this->assertNull($news->getDateCreation());
    }

    public function testNewsDefaultDateUpdatedIsNull(): void
    {
        $news = new News();
        $this->assertNull($news->getDateUpdated());
    }

    public function testNewsDefaultVisibilityIsEmptyArray(): void
    {
        $news = new News();
        $this->assertSame([], $news->getVisibility());
    }

    public function testNewsDefaultCreatorIsNull(): void
    {
        $news = new News();
        $this->assertNull($news->getCreator());
    }

    public function testNewsSetRvcodeReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setRvcode('epijinfo');
        $this->assertSame($news, $result);
    }

    public function testNewsGetRvcodeReturnsSetValue(): void
    {
        $news = new News();
        $news->setRvcode('myjournal');
        $this->assertSame('myjournal', $news->getRvcode());
    }

    public function testNewsSetUidReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setUid(42);
        $this->assertSame($news, $result);
    }

    public function testNewsGetUidReturnsSetValue(): void
    {
        $news = new News();
        $news->setUid(99);
        $this->assertSame(99, $news->getUid());
    }

    public function testNewsSetTitleReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setTitle(['en' => 'My title']);
        $this->assertSame($news, $result);
    }

    public function testNewsGetTitleReturnsSetValue(): void
    {
        $news = new News();
        $news->setTitle(['en' => 'Hello', 'fr' => 'Bonjour']);
        $this->assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $news->getTitle());
    }

    public function testNewsSetContentReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setContent(['en' => 'Content']);
        $this->assertSame($news, $result);
    }

    public function testNewsGetContentReturnsSetValue(): void
    {
        $news = new News();
        $news->setContent(['en' => 'Some content']);
        $this->assertSame(['en' => 'Some content'], $news->getContent());
    }

    public function testNewsSetContentWithNullResetsToNull(): void
    {
        $news = new News();
        $news->setContent(['en' => 'data']);
        $news->setContent(null);
        $this->assertNull($news->getContent());
    }

    public function testNewsSetLinkReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setLink(['en' => 'https://example.com']);
        $this->assertSame($news, $result);
    }

    public function testNewsGetLinkReturnsSetValue(): void
    {
        $news = new News();
        $news->setLink(['en' => 'https://episciences.org']);
        $this->assertSame(['en' => 'https://episciences.org'], $news->getLink());
    }

    public function testNewsSetDateCreationReturnsSelf(): void
    {
        $news = new News();
        $dt = new DateTime('2023-01-01');
        $result = $news->setDateCreation($dt);
        $this->assertSame($news, $result);
    }

    public function testNewsGetDateCreationReturnsSetValue(): void
    {
        $news = new News();
        $dt = new DateTime('2024-06-15');
        $news->setDateCreation($dt);
        $this->assertSame($dt, $news->getDateCreation());
    }

    public function testNewsSetDateUpdatedReturnsSelf(): void
    {
        $news = new News();
        $dt = new DateTime('2023-12-01');
        $result = $news->setDateUpdated($dt);
        $this->assertSame($news, $result);
    }

    public function testNewsGetDateUpdatedReturnsSetValue(): void
    {
        $news = new News();
        $dt = new DateTime('2024-01-01');
        $news->setDateUpdated($dt);
        $this->assertSame($dt, $news->getDateUpdated());
    }

    public function testNewsSetVisibilityReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setVisibility(['public']);
        $this->assertSame($news, $result);
    }

    public function testNewsGetVisibilityReturnsSetValue(): void
    {
        $news = new News();
        $news->setVisibility(['public', 'authenticated']);
        $this->assertSame(['public', 'authenticated'], $news->getVisibility());
    }

    public function testNewsSetCreatorReturnsSelf(): void
    {
        $news = new News();
        $user = $this->createStub(User::class);
        $result = $news->setCreator($user);
        $this->assertSame($news, $result);
    }

    public function testNewsGetCreatorReturnsSetValue(): void
    {
        $news = new News();
        $user = $this->createStub(User::class);
        $news->setCreator($user);
        $this->assertSame($user, $news->getCreator());
    }

    public function testNewsSetCreatorWithNullResetsToNull(): void
    {
        $news = new News();
        $news->setCreator($this->createStub(User::class));
        $news->setCreator(null);
        $this->assertNull($news->getCreator());
    }

    public function testNewsSetLegacyIdReturnsSelf(): void
    {
        $news = new News();
        $result = $news->setLegacyId(100);
        $this->assertSame($news, $result);
    }

    public function testNewsGetLegacyIdReturnsSetValue(): void
    {
        $news = new News();
        $news->setLegacyId(200);
        $this->assertSame(200, $news->getLegacyId());
    }

    public function testNewsSetLegacyIdWithNullResetsToNull(): void
    {
        $news = new News();
        $news->setLegacyId(50);
        $news->setLegacyId(null);
        $this->assertNull($news->getLegacyId());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function testPageTableConstant(): void
    {
        $this->assertSame('pages', Page::TABLE);
    }

    public function testPageFiltersHasUidKey(): void
    {
        $this->assertArrayHasKey('uid', Page::FILTERS);
        $this->assertSame(AppConstants::FILTER_TYPE_EXACT, Page::FILTERS['uid']);
    }

    public function testPageFiltersHasRvcodeKey(): void
    {
        $this->assertArrayHasKey('rvcode', Page::FILTERS);
    }

    public function testPageFiltersHasPageCodeKey(): void
    {
        $this->assertArrayHasKey('page_code', Page::FILTERS);
    }

    public function testPageDefaultTitleIsEmptyArray(): void
    {
        $page = new Page();
        $this->assertSame([], $page->getTitle());
    }

    public function testPageDefaultContentIsEmptyArray(): void
    {
        $page = new Page();
        $this->assertSame([], $page->getContent());
    }

    public function testPageDefaultVisibilityIsEmptyArray(): void
    {
        $page = new Page();
        $this->assertSame([], $page->getVisibility());
    }

    public function testPageDefaultDateCreationIsNull(): void
    {
        $page = new Page();
        $this->assertNull($page->getDateCreation());
    }

    public function testPageSetRvcodeReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setRvcode('epijinfo');
        $this->assertSame($page, $result);
    }

    public function testPageGetRvcodeReturnsSetValue(): void
    {
        $page = new Page();
        $page->setRvcode('myjournal');
        $this->assertSame('myjournal', $page->getRvcode());
    }

    public function testPageSetUidReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setUid(7);
        $this->assertSame($page, $result);
    }

    public function testPageGetUidReturnsSetValue(): void
    {
        $page = new Page();
        $page->setUid(42);
        $this->assertSame(42, $page->getUid());
    }

    public function testPageSetTitleReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setTitle(['en' => 'About']);
        $this->assertSame($page, $result);
    }

    public function testPageGetTitleReturnsSetValue(): void
    {
        $page = new Page();
        $page->setTitle(['en' => 'About Us', 'fr' => 'À propos']);
        $this->assertSame(['en' => 'About Us', 'fr' => 'À propos'], $page->getTitle());
    }

    public function testPageSetContentReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setContent(['en' => 'Content here']);
        $this->assertSame($page, $result);
    }

    public function testPageGetContentReturnsSetValue(): void
    {
        $page = new Page();
        $page->setContent(['en' => 'Body text']);
        $this->assertSame(['en' => 'Body text'], $page->getContent());
    }

    public function testPageSetVisibilityReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setVisibility(['public']);
        $this->assertSame($page, $result);
    }

    public function testPageGetVisibilityReturnsSetValue(): void
    {
        $page = new Page();
        $page->setVisibility(['authenticated']);
        $this->assertSame(['authenticated'], $page->getVisibility());
    }

    public function testPageSetDateCreationReturnsSelf(): void
    {
        $page = new Page();
        $dt = new DateTime('2024-01-01');
        $result = $page->setDateCreation($dt);
        $this->assertSame($page, $result);
    }

    public function testPageGetDateCreationReturnsSetValue(): void
    {
        $page = new Page();
        $dt = new DateTime('2023-08-15');
        $page->setDateCreation($dt);
        $this->assertSame($dt, $page->getDateCreation());
    }

    public function testPageSetDateUpdatedReturnsSelf(): void
    {
        $page = new Page();
        $dt = new DateTime('2024-12-31');
        $result = $page->setDateUpdated($dt);
        $this->assertSame($page, $result);
    }

    public function testPageGetDateUpdatedReturnsSetValue(): void
    {
        $page = new Page();
        $dt = new DateTime('2025-01-01');
        $page->setDateUpdated($dt);
        $this->assertSame($dt, $page->getDateUpdated());
    }

    public function testPageSetPageCodeReturnsSelf(): void
    {
        $page = new Page();
        $result = $page->setPageCode('about');
        $this->assertSame($page, $result);
    }

    public function testPageGetPageCodeReturnsSetValue(): void
    {
        $page = new Page();
        $page->setPageCode('contact');
        $this->assertSame('contact', $page->getPageCode());
    }
}
