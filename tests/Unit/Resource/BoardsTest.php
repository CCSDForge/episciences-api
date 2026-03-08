<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resource;

use App\Resource\Boards;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Boards resource DTO.
 */
final class BoardsTest extends TestCase
{
    // ── Constructor ───────────────────────────────────────────────────────────

    public function testConstructorDefaultsToNull(): void
    {
        $boards = new Boards();
        $this->assertNull($boards->getBoards());
    }

    public function testConstructorAcceptsBoardsArray(): void
    {
        $data = [['uid' => 1, 'role' => 'editor']];
        $boards = new Boards($data);
        $this->assertSame($data, $boards->getBoards());
    }

    public function testConstructorAcceptsEmptyArray(): void
    {
        $boards = new Boards([]);
        $this->assertSame([], $boards->getBoards());
    }

    // ── setBoards / getBoards ─────────────────────────────────────────────────

    public function testSetAndGetBoards(): void
    {
        $boards = new Boards();
        $data = [['uid' => 10, 'role' => 'managing_editor']];
        $result = $boards->setBoards($data);
        $this->assertSame($boards, $result, 'setBoards() must return self');
        $this->assertSame($data, $boards->getBoards());
    }

    public function testSetBoardsToNull(): void
    {
        $boards = new Boards([['uid' => 1]]);
        $boards->setBoards(null);
        $this->assertNull($boards->getBoards());
    }

    public function testSetBoardsDefaultParameterIsNull(): void
    {
        $boards = new Boards([['uid' => 1]]);
        $boards->setBoards();
        $this->assertNull($boards->getBoards());
    }

    public function testSetBoardsWithMultipleEntries(): void
    {
        $boards = new Boards();
        $data = [
            ['uid' => 1, 'roleid' => 'editor'],
            ['uid' => 2, 'roleid' => 'managing_editor'],
            ['uid' => 3, 'roleid' => 'technical_board'],
        ];
        $boards->setBoards($data);
        $this->assertCount(3, $boards->getBoards());
    }
}
