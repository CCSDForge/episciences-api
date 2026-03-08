<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Entity\Review;
use App\Resource\Browse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Browse resource (and AbstractBrowse base class).
 *
 * Covers:
 * - Browse constants: AVAILABLE_VALUES_TO_PREFIX_DESCRIPTION, BROWSE_AUTHORS_COLLECTION_IDENTIFIER
 * - setJournal() / getJournal() (from AbstractBrowse)
 * - Fluent interface on setJournal()
 * - Null reset on setJournal()
 */
final class BrowseTest extends TestCase
{
    private Browse $browse;

    protected function setUp(): void
    {
        $this->browse = new Browse();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testAvailableValuesToPrefixDescriptionConstant(): void
    {
        $this->assertSame(
            'Prefixed with a letter: available values [A...Z, others, all]',
            Browse::AVAILABLE_VALUES_TO_PREFIX_DESCRIPTION
        );
    }

    public function testBrowseAuthorsCollectionIdentifierConstant(): void
    {
        $this->assertSame('/api/browse/authors/', Browse::BROWSE_AUTHORS_COLLECTION_IDENTIFIER);
    }

    // ── setJournal / getJournal (AbstractBrowse) ──────────────────────────────

    public function testGetJournalDefaultsToNull(): void
    {
        $this->assertNull($this->browse->getJournal());
    }

    public function testSetJournalReturnsSelf(): void
    {
        $journal = $this->createStub(Review::class);
        $result = $this->browse->setJournal($journal);
        $this->assertSame($this->browse, $result);
    }

    public function testSetJournalStoresValue(): void
    {
        $journal = $this->createStub(Review::class);
        $this->browse->setJournal($journal);
        $this->assertSame($journal, $this->browse->getJournal());
    }

    public function testSetJournalWithNullResetsToNull(): void
    {
        $journal = $this->createStub(Review::class);
        $this->browse->setJournal($journal);
        $this->browse->setJournal(null);
        $this->assertNull($this->browse->getJournal());
    }

    public function testSetJournalWithDefaultArgResetsToNull(): void
    {
        $journal = $this->createStub(Review::class);
        $this->browse->setJournal($journal);
        $this->browse->setJournal(); // uses default null
        $this->assertNull($this->browse->getJournal());
    }

    public function testSetJournalFluentChainingWorks(): void
    {
        $journal1 = $this->createStub(Review::class);
        $journal2 = $this->createStub(Review::class);

        $this->browse->setJournal($journal1)->setJournal($journal2);
        $this->assertSame($journal2, $this->browse->getJournal());
    }

    public function testSetDifferentJournalsOverwritesPrevious(): void
    {
        $j1 = $this->createStub(Review::class);
        $j2 = $this->createStub(Review::class);

        $this->browse->setJournal($j1);
        $this->assertSame($j1, $this->browse->getJournal());

        $this->browse->setJournal($j2);
        $this->assertSame($j2, $this->browse->getJournal());
    }
}
