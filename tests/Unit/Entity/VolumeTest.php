<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Paper;
use App\Entity\Volume;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Volume entity.
 *
 * Covers:
 * - Constants: TABLE, DEFAULT_URI_TEMPLATE
 * - setOptions() constructor dispatch (inherited from AbstractVolumeSection)
 * - getTitles() fallback when titles is null (uses vid)
 * - getPapers(forceSort: false) vs getPapers(forceSort: true) — sorting by position then paperid
 * - setTotalPublishedArticles() counting via AbstractVolumeSection
 * - Simple getters/setters: vid, rvid, position, vol_year, vol_num, vol_type,
 *   bibReference, descriptions, titles
 * - getMetadata() initial state
 * - getSettings() / getSettingsProceeding() initial state
 */
final class VolumeTest extends TestCase
{
    private Volume $volume;

    protected function setUp(): void
    {
        $this->volume = new Volume();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('VOLUME', Volume::TABLE);
    }

    public function testDefaultUriTemplateConstant(): void
    {
        $this->assertSame('/volumes{._format}', Volume::DEFAULT_URI_TEMPLATE);
    }

    // ── setOptions constructor dispatch ───────────────────────────────────────

    public function testConstructorDispatchesSetRvid(): void
    {
        $vol = new Volume(['rvid' => 5]);
        $this->assertSame(5, $vol->getRvid());
    }

    public function testConstructorDispatchesSetPosition(): void
    {
        $vol = new Volume(['position' => 3]);
        $this->assertSame(3, $vol->getPosition());
    }

    public function testConstructorDispatchesSetVolYear(): void
    {
        $vol = new Volume(['vol_year' => '2023']);
        $this->assertSame('2023', $vol->getVolYear());
    }

    public function testConstructorIgnoresUnknownKeys(): void
    {
        // Unknown keys are silently skipped; getDescriptions() does not depend on vid
        $vol = new Volume(['completely_unknown' => 'ignored']);
        $this->assertNull($vol->getDescriptions());
    }

    // ── setVid / getVid ───────────────────────────────────────────────────────

    public function testSetVidReturnsSelf(): void
    {
        $result = $this->volume->setVid(1);
        $this->assertSame($this->volume, $result);
    }

    public function testGetVidReturnsSetValue(): void
    {
        $this->volume->setVid(42);
        $this->assertSame(42, $this->volume->getVid());
    }

    // ── setRvid / getRvid ─────────────────────────────────────────────────────

    public function testSetRvidReturnsSelf(): void
    {
        $result = $this->volume->setRvid(10);
        $this->assertSame($this->volume, $result);
    }

    public function testGetRvidReturnsSetValue(): void
    {
        $this->volume->setRvid(7);
        $this->assertSame(7, $this->volume->getRvid());
    }

    // ── setPosition / getPosition ─────────────────────────────────────────────

    public function testSetPositionReturnsSelf(): void
    {
        $result = $this->volume->setPosition(2);
        $this->assertSame($this->volume, $result);
    }

    public function testGetPositionReturnsSetValue(): void
    {
        $this->volume->setPosition(99);
        $this->assertSame(99, $this->volume->getPosition());
    }

    // ── getTitles() fallback ──────────────────────────────────────────────────

    /**
     * When $titles is null (no titles set) and a vid is set, getTitles() must
     * return a fallback array of the form ['en' => 'volume_{vid}_title'].
     */
    public function testGetTitlesFallbackWhenNullAndVidIsSet(): void
    {
        $this->volume->setVid(99);
        $titles = $this->volume->getTitles();
        $this->assertIsArray($titles);
        $this->assertArrayHasKey('en', $titles);
        $this->assertSame('volume_99_title', $titles['en']);
    }

    public function testGetTitlesReturnSetValueWhenNotNull(): void
    {
        $this->volume->setTitles(['en' => 'My Volume', 'fr' => 'Mon Volume']);
        $titles = $this->volume->getTitles();
        $this->assertSame(['en' => 'My Volume', 'fr' => 'Mon Volume'], $titles);
    }

    public function testSetTitlesReturnsSelf(): void
    {
        $result = $this->volume->setTitles(['en' => 'Title']);
        $this->assertSame($this->volume, $result);
    }

    // ── getDescriptions / setDescriptions ─────────────────────────────────────

    public function testGetDescriptionsDefaultsToNull(): void
    {
        $this->assertNull($this->volume->getDescriptions());
    }

    public function testSetDescriptionsReturnsSelf(): void
    {
        $result = $this->volume->setDescriptions(['en' => 'Desc']);
        $this->assertSame($this->volume, $result);
    }

    public function testSetDescriptionsStoresValue(): void
    {
        $this->volume->setDescriptions(['en' => 'A description']);
        $this->assertSame(['en' => 'A description'], $this->volume->getDescriptions());
    }

    // ── getBibReference / setBibReference ─────────────────────────────────────

    public function testSetBibReferenceReturnsSelf(): void
    {
        $result = $this->volume->setBibReference('vol-2023-1');
        $this->assertSame($this->volume, $result);
    }

    public function testGetBibReferenceReturnsSetValue(): void
    {
        $this->volume->setBibReference('vol-ref-42');
        $this->assertSame('vol-ref-42', $this->volume->getBibReference());
    }

    public function testSetBibReferenceWithEmptyString(): void
    {
        $this->volume->setBibReference('');
        $this->assertSame('', $this->volume->getBibReference());
    }

    // ── getPapers(forceSort) sorting logic ────────────────────────────────────

    /**
     * getPapers(forceSort: true) must sort papers by position ASC, then paperid ASC
     * when positions are equal.
     */
    public function testGetPapersWithForceSortReturnsPapersSortedByPosition(): void
    {
        $p1 = $this->createMock(Paper::class);
        $p1->method('getPaperPosition')->willReturn(2);
        $p1->method('getPaperid')->willReturn(10);
        $p1->method('getVolume')->willReturn($this->volume);

        $p2 = $this->createMock(Paper::class);
        $p2->method('getPaperPosition')->willReturn(1);
        $p2->method('getPaperid')->willReturn(20);
        $p2->method('getVolume')->willReturn($this->volume);

        $this->volume->addPaper($p1);
        $this->volume->addPaper($p2);

        $sorted = $this->volume->getPapers(true)->toArray();

        // p2 (position=1) should come before p1 (position=2)
        $this->assertSame($p2, $sorted[0]);
        $this->assertSame($p1, $sorted[1]);
    }

    public function testGetPapersWithForceSortBreaksTiesByPaperid(): void
    {
        $p1 = $this->createMock(Paper::class);
        $p1->method('getPaperPosition')->willReturn(1);
        $p1->method('getPaperid')->willReturn(20);
        $p1->method('getVolume')->willReturn($this->volume);

        $p2 = $this->createMock(Paper::class);
        $p2->method('getPaperPosition')->willReturn(1);
        $p2->method('getPaperid')->willReturn(10);
        $p2->method('getVolume')->willReturn($this->volume);

        $this->volume->addPaper($p1);
        $this->volume->addPaper($p2);

        $sorted = $this->volume->getPapers(true)->toArray();

        // Same position — lower paperid comes first
        $this->assertSame($p2, $sorted[0]);
        $this->assertSame($p1, $sorted[1]);
    }

    public function testGetPapersWithForceSortPutsNullPositionLast(): void
    {
        $p1 = $this->createMock(Paper::class);
        $p1->method('getPaperPosition')->willReturn(null); // PHP_INT_MAX position
        $p1->method('getPaperid')->willReturn(5);
        $p1->method('getVolume')->willReturn($this->volume);

        $p2 = $this->createMock(Paper::class);
        $p2->method('getPaperPosition')->willReturn(1);
        $p2->method('getPaperid')->willReturn(10);
        $p2->method('getVolume')->willReturn($this->volume);

        $this->volume->addPaper($p1);
        $this->volume->addPaper($p2);

        $sorted = $this->volume->getPapers(true)->toArray();

        // p2 (explicit position=1) must come before p1 (null → PHP_INT_MAX)
        $this->assertSame($p2, $sorted[0]);
        $this->assertSame($p1, $sorted[1]);
    }

    public function testGetPapersWithoutForceSortReturnsUnchangedCollection(): void
    {
        $p1 = $this->createMock(Paper::class);
        $p1->method('getVolume')->willReturn($this->volume);

        $this->volume->addPaper($p1);

        // forceSort = false (default) → returns the stored collection directly
        $papers = $this->volume->getPapers(false);
        $this->assertCount(1, $papers);
    }

    // ── setTotalPublishedArticles (inherited) ─────────────────────────────────

    public function testSetTotalPublishedArticlesCountsOnlyPublished(): void
    {
        $pub = $this->createMock(Paper::class);
        $pub->method('isPublished')->willReturn(true);
        $pub->method('getVolume')->willReturn($this->volume);

        $notPub = $this->createMock(Paper::class);
        $notPub->method('isPublished')->willReturn(false);
        $notPub->method('getVolume')->willReturn($this->volume);

        $this->volume->addPaper($pub);
        $this->volume->addPaper($notPub);
        $this->volume->setTotalPublishedArticles();

        $this->assertSame(1, $this->volume->getTotalPublishedArticles());
    }

    // ── getMetadata / getSettings / getSettingsProceeding initial state ────────

    public function testGetMetadataInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->volume->getMetadata());
    }

    public function testGetSettingsInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->volume->getSettings());
    }

    public function testGetSettingsProceedingInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->volume->getSettingsProceeding());
    }

    // ── addPaper / getPapers initial state ────────────────────────────────────

    public function testGetPapersInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->volume->getPapers());
    }

    public function testAddPaperReturnsSelf(): void
    {
        $paper = $this->createMock(Paper::class);
        $paper->method('getVolume')->willReturn(null);
        $result = $this->volume->addPaper($paper);
        $this->assertSame($this->volume, $result);
    }

    public function testAddPaperDoesNotAddDuplicate(): void
    {
        $paper = $this->createMock(Paper::class);
        $paper->method('getVolume')->willReturn($this->volume);
        $this->volume->addPaper($paper);
        $this->volume->addPaper($paper); // same instance
        $this->assertCount(1, $this->volume->getPapers());
    }
}
