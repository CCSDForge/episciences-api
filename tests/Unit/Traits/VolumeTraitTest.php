<?php

declare(strict_types=1);

namespace App\Tests\Unit\Traits;

use App\Entity\Paper;
use App\Traits\VolumeTrait;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class exposing VolumeTrait (which uses QueryTrait internally).
 */
final class ConcreteVolumeTrait
{
    use VolumeTrait;
}

final class VolumeTraitTest extends TestCase
{
    private ConcreteVolumeTrait $subject;

    protected function setUp(): void
    {
        $this->subject = new ConcreteVolumeTrait();
    }

    // ── addWhere: rvId filter ─────────────────────────────────────────────────

    public function testAddWhereWithRvIdSetsParameter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('p.rvid = :rvId')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('rvId', 7)
            ->willReturnSelf();

        $this->subject->addWhere(7, $qb, false, null);
    }

    public function testAddWhereWithNullRvIdSkipsRvIdFilter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        $this->subject->addWhere(null, $qb, false, null);
    }

    // ── addWhere: strictlyPublished filter ────────────────────────────────────

    public function testAddWhereStrictlyPublishedAddsStatusFilter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('p.status = :status')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', Paper::STATUS_PUBLISHED)
            ->willReturnSelf();

        $this->subject->addWhere(null, $qb, true, null);
    }

    public function testAddWhereNotStrictlyPublishedSkipsStatusFilter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        // andWhere should never be called if no filters active
        $qb->expects($this->never())->method('andWhere');

        $this->subject->addWhere(null, $qb, false, null);
    }

    // ── addWhere: ids filter ──────────────────────────────────────────────────

    public function testAddWhereWithIntIdWrapsInArray(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getAllAliases')->willReturn(['p']);
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturnSelf();

        $this->subject->addWhere(null, $qb, false, 5);
    }

    public function testAddWhereWithArrayIdsAddsOrExpression(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getAllAliases')->willReturn(['p']);
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturnSelf();

        $this->subject->addWhere(null, $qb, false, [1, 2, 3]);
    }

    public function testAddWhereWithNullIdsSkipsIdsFilter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('getAllAliases');
        $qb->expects($this->never())->method('andWhere');

        $this->subject->addWhere(null, $qb, false, null);
    }
}
