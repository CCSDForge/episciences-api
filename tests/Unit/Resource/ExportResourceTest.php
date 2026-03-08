<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Entity\Review;
use App\Resource\Export;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Export resource (and AbstractBrowse base class).
 *
 * Covers getters/setters for docId, format, and journal (inherited).
 */
final class ExportResourceTest extends TestCase
{
    private Export $export;

    protected function setUp(): void
    {
        $this->export = new Export();
    }

    // ── AbstractBrowse: getJournal / setJournal ───────────────────────────────

    public function testGetJournalReturnsNullByDefault(): void
    {
        $this->assertNull($this->export->getJournal());
    }

    public function testSetJournalReturnsSelf(): void
    {
        $journal = $this->createStub(Review::class);
        $result = $this->export->setJournal($journal);
        $this->assertSame($this->export, $result);
    }

    public function testGetJournalReturnsSetJournal(): void
    {
        $journal = $this->createStub(Review::class);
        $this->export->setJournal($journal);
        $this->assertSame($journal, $this->export->getJournal());
    }

    public function testSetJournalWithNullClearsJournal(): void
    {
        $journal = $this->createStub(Review::class);
        $this->export->setJournal($journal);
        $this->export->setJournal(null);
        $this->assertNull($this->export->getJournal());
    }

    // ── Export: format ────────────────────────────────────────────────────────

    public function testGetFormatReturnsNullByDefault(): void
    {
        $this->assertNull($this->export->getFormat());
    }

    public function testSetFormatReturnsSelf(): void
    {
        $result = $this->export->setFormat('bibtex');
        $this->assertSame($this->export, $result);
    }

    public function testGetFormatReturnsSetValue(): void
    {
        $this->export->setFormat('tei');
        $this->assertSame('tei', $this->export->getFormat());
    }

    public function testSetFormatWithNullResetsToNull(): void
    {
        $this->export->setFormat('csl');
        $this->export->setFormat(null);
        $this->assertNull($this->export->getFormat());
    }

    // ── Export: docId ─────────────────────────────────────────────────────────

    public function testSetDocIdReturnsSelf(): void
    {
        $result = $this->export->setDocId(42);
        $this->assertSame($this->export, $result);
    }

    public function testGetDocIdReturnsSetValue(): void
    {
        $this->export->setDocId(1234);
        $this->assertSame(1234, $this->export->getDocId());
    }

    public function testGetDocIdReturnsZeroWhenSetToZero(): void
    {
        $this->export->setDocId(0);
        $this->assertSame(0, $this->export->getDocId());
    }
}
