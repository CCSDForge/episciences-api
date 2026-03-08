<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Paper;
use App\Entity\Section;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Section entity (and AbstractVolumeSection base class).
 *
 * Covers:
 * - Constants: TABLE, DEFAULT_URI_TEMPLATE
 * - setOptions() dynamic dispatch (from AbstractVolumeSection)
 * - setTotalPublishedArticles() counting logic (from AbstractVolumeSection)
 * - setCommittee() / getCommittee()
 * - Simple getters/setters: rvid, sid, position, titles, descriptions
 * - getTitles() / getDescriptions() null handling
 * - getIdentifier() returns sid
 * - getSettings() initial state
 */
final class SectionTest extends TestCase
{
    private Section $section;

    protected function setUp(): void
    {
        $this->section = new Section();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('SECTION', Section::TABLE);
    }

    public function testDefaultUriTemplateConstant(): void
    {
        $this->assertSame('/sections{._format}', Section::DEFAULT_URI_TEMPLATE);
    }

    // ── setOptions (AbstractVolumeSection) ────────────────────────────────────

    /**
     * setOptions() converts snake_case keys to CamelCase and calls the
     * corresponding setter if it exists.
     */
    public function testSetOptionsDispatchesToSetRvid(): void
    {
        $section = new Section(['rvid' => 42]);
        $this->assertSame(42, $section->getRvid());
    }

    public function testSetOptionsDispatchesToSetPosition(): void
    {
        $section = new Section(['position' => 7]);
        $this->assertSame(7, $section->getPosition());
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        // Should not throw; unknown keys are silently skipped
        $section = new Section(['nonexistent_key' => 'value']);
        $this->assertNull($section->getTitles());
    }

    public function testSetOptionsDispatchesToSetTitles(): void
    {
        $section = new Section(['titles' => ['en' => 'My Section']]);
        $this->assertSame(['en' => 'My Section'], $section->getTitles());
    }

    // ── setTotalPublishedArticles (AbstractVolumeSection) ─────────────────────

    public function testSetTotalPublishedArticlesCountsOnlyPublishedPapers(): void
    {
        $published1 = $this->createMock(Paper::class);
        $published1->method('isPublished')->willReturn(true);
        $published1->method('getSection')->willReturn($this->section);

        $published2 = $this->createMock(Paper::class);
        $published2->method('isPublished')->willReturn(true);
        $published2->method('getSection')->willReturn($this->section);

        $notPublished = $this->createMock(Paper::class);
        $notPublished->method('isPublished')->willReturn(false);
        $notPublished->method('getSection')->willReturn($this->section);

        // Use setPapers via addPaper to populate the internal collection
        $this->section->addPaper($published1);
        $this->section->addPaper($published2);
        $this->section->addPaper($notPublished);

        $this->section->setTotalPublishedArticles();

        $this->assertSame(2, $this->section->getTotalPublishedArticles());
    }

    public function testSetTotalPublishedArticlesReturnsZeroWhenNoPapers(): void
    {
        $this->section->setTotalPublishedArticles();
        $this->assertSame(0, $this->section->getTotalPublishedArticles());
    }

    public function testSetTotalPublishedArticlesReturnsSelf(): void
    {
        $result = $this->section->setTotalPublishedArticles();
        $this->assertSame($this->section, $result);
    }

    // ── setCommittee / getCommittee (AbstractVolumeSection) ──────────────────

    public function testSetCommitteeStoresValue(): void
    {
        $committee = [['name' => 'John Doe', 'role' => 'editor']];
        $this->section->setCommittee($committee);
        $this->assertSame($committee, $this->section->getCommittee());
    }

    public function testSetCommitteeReturnsSelf(): void
    {
        $result = $this->section->setCommittee([]);
        $this->assertSame($this->section, $result);
    }

    public function testSetCommitteeWithEmptyArray(): void
    {
        $this->section->setCommittee([]);
        $this->assertSame([], $this->section->getCommittee());
    }

    // ── setRvid / getRvid ─────────────────────────────────────────────────────

    public function testSetRvidReturnsSelf(): void
    {
        $result = $this->section->setRvid(5);
        $this->assertSame($this->section, $result);
    }

    public function testGetRvidReturnsSetValue(): void
    {
        $this->section->setRvid(99);
        $this->assertSame(99, $this->section->getRvid());
    }

    // ── setSid / getSid / getIdentifier ──────────────────────────────────────

    public function testSetSidReturnsSelf(): void
    {
        $result = $this->section->setSid(10);
        $this->assertSame($this->section, $result);
    }

    public function testGetSidReturnsSetValue(): void
    {
        $this->section->setSid(42);
        $this->assertSame(42, $this->section->getSid());
    }

    public function testGetIdentifierReturnsSid(): void
    {
        $this->section->setSid(7);
        $this->assertSame(7, $this->section->getIdentifier());
    }

    // ── setPosition / getPosition ─────────────────────────────────────────────

    public function testSetPositionReturnsSelf(): void
    {
        $result = $this->section->setPosition(3);
        $this->assertSame($this->section, $result);
    }

    public function testGetPositionReturnsSetValue(): void
    {
        $this->section->setPosition(5);
        $this->assertSame(5, $this->section->getPosition());
    }

    // ── setTitles / getTitles ─────────────────────────────────────────────────

    public function testGetTitlesDefaultsToNull(): void
    {
        $this->assertNull($this->section->getTitles());
    }

    public function testSetTitlesReturnsSelf(): void
    {
        $result = $this->section->setTitles(['en' => 'Title']);
        $this->assertSame($this->section, $result);
    }

    public function testSetTitlesWithNullResetsToNull(): void
    {
        $this->section->setTitles(['en' => 'Title']);
        $this->section->setTitles(null);
        $this->assertNull($this->section->getTitles());
    }

    // ── setDescriptions / getDescriptions ─────────────────────────────────────

    public function testGetDescriptionsDefaultsToNull(): void
    {
        $this->assertNull($this->section->getDescriptions());
    }

    public function testSetDescriptionsReturnsSelf(): void
    {
        $result = $this->section->setDescriptions(['en' => 'Desc']);
        $this->assertSame($this->section, $result);
    }

    public function testSetDescriptionsWithNullResetsToNull(): void
    {
        $this->section->setDescriptions(['en' => 'Desc']);
        $this->section->setDescriptions(null);
        $this->assertNull($this->section->getDescriptions());
    }

    // ── getPapers / addPaper ──────────────────────────────────────────────────

    public function testGetPapersInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->section->getPapers());
    }

    public function testAddPaperReturnsSelf(): void
    {
        $paper = $this->createMock(Paper::class);
        $paper->method('getSection')->willReturn(null);
        $result = $this->section->addPaper($paper);
        $this->assertSame($this->section, $result);
    }

    public function testAddPaperDoesNotAddDuplicate(): void
    {
        $paper = $this->createMock(Paper::class);
        $paper->method('getSection')->willReturn($this->section);
        $this->section->addPaper($paper);
        $this->section->addPaper($paper);
        $this->assertCount(1, $this->section->getPapers());
    }

    // ── getSettings ───────────────────────────────────────────────────────────

    public function testGetSettingsInitiallyEmpty(): void
    {
        $this->assertCount(0, $this->section->getSettings());
    }
}
