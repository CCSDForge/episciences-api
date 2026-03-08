<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PaperLog;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PaperLog entity.
 *
 * Covers:
 * - TABLE constant
 * - All setters/getters (paperid, docid, uid, rvid, action, detail, file, date)
 * - Fluent interface on all setters
 * - Null handling for optional fields (detail, file)
 */
final class PaperLogTest extends TestCase
{
    private PaperLog $log;

    protected function setUp(): void
    {
        $this->log = new PaperLog();
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testTableConstant(): void
    {
        $this->assertSame('PAPER_LOG', PaperLog::TABLE);
    }

    // ── setPaperid / getPaperid ───────────────────────────────────────────────

    public function testSetPaperidReturnsSelf(): void
    {
        $result = $this->log->setPaperid(1);
        $this->assertSame($this->log, $result);
    }

    public function testGetPaperidReturnsSetValue(): void
    {
        $this->log->setPaperid(42);
        $this->assertSame(42, $this->log->getPaperid());
    }

    // ── setDocid / getDocid ───────────────────────────────────────────────────

    public function testSetDocidReturnsSelf(): void
    {
        $result = $this->log->setDocid(10);
        $this->assertSame($this->log, $result);
    }

    public function testGetDocidReturnsSetValue(): void
    {
        $this->log->setDocid(99);
        $this->assertSame(99, $this->log->getDocid());
    }

    // ── setUid / getUid ───────────────────────────────────────────────────────

    public function testSetUidReturnsSelf(): void
    {
        $result = $this->log->setUid(5);
        $this->assertSame($this->log, $result);
    }

    public function testGetUidReturnsSetValue(): void
    {
        $this->log->setUid(7);
        $this->assertSame(7, $this->log->getUid());
    }

    // ── setRvid / getRvid ─────────────────────────────────────────────────────

    public function testSetRvidReturnsSelf(): void
    {
        $result = $this->log->setRvid(3);
        $this->assertSame($this->log, $result);
    }

    public function testGetRvidReturnsSetValue(): void
    {
        $this->log->setRvid(100);
        $this->assertSame(100, $this->log->getRvid());
    }

    // ── setAction / getAction ─────────────────────────────────────────────────

    public function testSetActionReturnsSelf(): void
    {
        $result = $this->log->setAction('submit');
        $this->assertSame($this->log, $result);
    }

    public function testGetActionReturnsSetValue(): void
    {
        $this->log->setAction('accept');
        $this->assertSame('accept', $this->log->getAction());
    }

    // ── setDetail / getDetail ─────────────────────────────────────────────────

    public function testGetDetailDefaultsToNull(): void
    {
        $this->assertNull($this->log->getDetail());
    }

    public function testSetDetailReturnsSelf(): void
    {
        $result = $this->log->setDetail('{"status":4}');
        $this->assertSame($this->log, $result);
    }

    public function testGetDetailReturnsSetValue(): void
    {
        $this->log->setDetail('{"status":16}');
        $this->assertSame('{"status":16}', $this->log->getDetail());
    }

    public function testSetDetailWithNullResetsToNull(): void
    {
        $this->log->setDetail('data');
        $this->log->setDetail(null);
        $this->assertNull($this->log->getDetail());
    }

    // ── setFile / getFile ─────────────────────────────────────────────────────

    public function testGetFileDefaultsToNull(): void
    {
        $this->assertNull($this->log->getFile());
    }

    public function testSetFileReturnsSelf(): void
    {
        $result = $this->log->setFile('paper.pdf');
        $this->assertSame($this->log, $result);
    }

    public function testGetFileReturnsSetValue(): void
    {
        $this->log->setFile('document_v2.pdf');
        $this->assertSame('document_v2.pdf', $this->log->getFile());
    }

    public function testSetFileWithNullResetsToNull(): void
    {
        $this->log->setFile('file.pdf');
        $this->log->setFile(null);
        $this->assertNull($this->log->getFile());
    }

    // ── setDate / getDate ─────────────────────────────────────────────────────

    public function testSetDateReturnsSelf(): void
    {
        $dt = new DateTime('2023-03-01');
        $result = $this->log->setDate($dt);
        $this->assertSame($this->log, $result);
    }

    public function testGetDateReturnsSetValue(): void
    {
        $dt = new DateTime('2022-11-25');
        $this->log->setDate($dt);
        $this->assertSame($dt, $this->log->getDate());
    }
}
