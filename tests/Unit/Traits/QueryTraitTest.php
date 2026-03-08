<?php

declare(strict_types=1);

namespace App\Tests\Unit\Traits;

use App\Entity\News;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use App\Traits\QueryTrait;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Concrete testable class that exposes all QueryTrait methods.
 */
final class ConcreteQueryTrait
{
    use QueryTrait;
}

final class QueryTraitTest extends TestCase
{
    private ConcreteQueryTrait $subject;

    protected function setUp(): void
    {
        $this->subject = new ConcreteQueryTrait();
    }

    // ── processYears ──────────────────────────────────────────────────────────

    public function testProcessYearsWithStringReturnsArrayWithSingleYear(): void
    {
        $result = $this->subject->processYears('2023');
        $this->assertSame(['2023'], array_values($result));
    }

    public function testProcessYearsDeduplicates(): void
    {
        $result = $this->subject->processYears(['2021', '2022', '2021']);
        $this->assertCount(2, $result);
        $this->assertContains('2021', $result);
        $this->assertContains('2022', $result);
    }

    public function testProcessYearsFiltersEmptyValues(): void
    {
        $result = $this->subject->processYears(['2021', '', null, '2022']);
        $this->assertCount(2, $result);
        $this->assertNotContains('', $result);
    }

    public function testProcessYearsWithEmptyArrayReturnsEmpty(): void
    {
        $result = $this->subject->processYears([]);
        $this->assertSame([], $result);
    }

    public function testProcessYearsWithDefaultReturnsEmpty(): void
    {
        $result = $this->subject->processYears();
        $this->assertSame([], $result);
    }

    // ── processOrExpression ───────────────────────────────────────────────────

    public function testProcessOrExpressionWithEmptyValuesReturnsSameQb(): void
    {
        $qb = $this->createMockQueryBuilder(Volume::class);
        $result = $this->subject->processOrExpression($qb, 'v', [], Volume::class);
        $this->assertSame($qb, $result);
    }

    public function testProcessOrExpressionWithVolumeClassAddsOrExpression(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->method('andWhere')->willReturnSelf();

        $result = $this->subject->processOrExpression($qb, 'v', ['2021', '2022'], Volume::class);
        $this->assertSame($qb, $result);
    }

    public function testProcessOrExpressionWithNewsClassAddsOrExpression(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->method('andWhere')->willReturnSelf();

        $result = $this->subject->processOrExpression($qb, 'n', ['2022'], News::class);
        $this->assertSame($qb, $result);
    }

    public function testProcessOrExpressionWithUnsupportedClassReturnsSameQb(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $result = $this->subject->processOrExpression($qb, 'x', ['2022'], Section::class);
        $this->assertSame($qb, $result);
    }

    // ── andOrExp ─────────────────────────────────────────────────────────────

    public function testAndOrExpWithEmptyValuesAndAllowedReturnsSameQb(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        // No andWhere should be called
        $qb->expects($this->never())->method('andWhere');

        $result = $this->subject->andOrExp($qb, 'v.year', [], true);
        $this->assertSame($qb, $result);
    }

    public function testAndOrExpWithEmptyValuesAndNotAllowedAddsAlwaysFalse(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnSelf();

        $result = $this->subject->andOrExp($qb, 'v.year', [], false);
        $this->assertSame($qb, $result);
    }

    public function testAndOrExpWithValuesAddsOrExpression(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturnSelf();

        $result = $this->subject->andOrExp($qb, 'v.vid', [1, 2, 3]);
        $this->assertSame($qb, $result);
    }

    // ── andOrLikeExp ─────────────────────────────────────────────────────────

    public function testAndOrLikeExpWithEmptyValuesAndAllowedReturnsSameQb(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        $result = $this->subject->andOrLikeExp($qb, 'v.vol_type', [], true, true);
        $this->assertSame($qb, $result);
    }

    public function testAndOrLikeExpWithEmptyValuesAndNotAllowedAddsAlwaysFalse(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnSelf();

        $result = $this->subject->andOrLikeExp($qb, 'v.vol_type', [], true, false);
        $this->assertSame($qb, $result);
    }

    public function testAndOrLikeExpWithValuesCaseInsensitiveAddsLikeExpression(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturnSelf();

        $result = $this->subject->andOrLikeExp($qb, 'v.vol_type', ['proceedings'], true);
        $this->assertSame($qb, $result);
    }

    public function testAndOrLikeExpWithValuesCaseSensitivePreservesCase(): void
    {
        $expr = new Expr();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturnSelf();

        $result = $this->subject->andOrLikeExp($qb, 'v.vol_type', ['PROCEEDINGS'], false);
        $this->assertSame($qb, $result);
    }

    // ── processTypes ─────────────────────────────────────────────────────────

    /**
     * Build a QB mock with an EntityManager mock that returns a VolumeRepository
     * stub reporting $availableTypes from getTypes().
     */
    private function makeQbWithVolTypes(array $availableTypes): QueryBuilder
    {
        $volRepo = $this->createMock(VolumeRepository::class);
        $volRepo->method('getTypes')->willReturn($availableTypes);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Volume::class)->willReturn($volRepo);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }

    public function testProcessTypesWithStringInputWrapsInArray(): void
    {
        $qb = $this->makeQbWithVolTypes(['proceedings', 'special']);
        $result = $this->subject->processTypes($qb, 'proceedings'); // string input
        $this->assertSame(['proceedings'], $result);
    }

    public function testProcessTypesReturnsAvailableTypesWhenAllExist(): void
    {
        $qb = $this->makeQbWithVolTypes(['proceedings', 'special', 'thematic']);
        $result = $this->subject->processTypes($qb, ['proceedings', 'special']);
        $this->assertSame(['proceedings', 'special'], $result);
    }

    public function testProcessTypesReturnsOnlyAvailableFilters(): void
    {
        $qb = $this->makeQbWithVolTypes(['proceedings']);
        $result = $this->subject->processTypes($qb, ['proceedings', 'unknown']);
        $this->assertSame(['proceedings'], $result);
    }

    public function testProcessTypesReturnsUnavailableWhenNoneMatch(): void
    {
        $qb = $this->makeQbWithVolTypes(['proceedings']);
        $result = $this->subject->processTypes($qb, ['unknown', 'other']);
        // availableCurrentTypes === [] → returns unavailableCurrentTypes
        $this->assertSame(['unknown', 'other'], $result);
    }

    public function testProcessTypesDeduplicatesInputFilters(): void
    {
        $qb = $this->makeQbWithVolTypes(['proceedings']);
        $result = $this->subject->processTypes($qb, ['proceedings', 'proceedings', 'proceedings']);
        $this->assertCount(1, $result);
        $this->assertSame(['proceedings'], $result);
    }

    // ── getIdentifiers ────────────────────────────────────────────────────────

    private function makeQbForIdentifiers(string $rootEntity, array $arrayResult = []): QueryBuilder
    {
        $ormQuery = $this->createMock(AbstractQuery::class);
        $ormQuery->method('getArrayResult')->willReturn($arrayResult);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn([$rootEntity]);
        $qb->method('getRootAliases')->willReturn(['v']);
        $qb->method('select')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($ormQuery);
        // For processOrExpression and andOrLikeExp (processTypes) may need expr()
        $qb->method('expr')->willReturn(new Expr());

        return $qb;
    }

    public function testGetIdentifiersReturnsEmptyWhenRootEntityIsNotVolumeOrSection(): void
    {
        $qb = $this->makeQbForIdentifiers(News::class);
        $result = $this->subject->getIdentifiers($qb, []);
        $this->assertSame([], $result);
    }

    public function testGetIdentifiersReturnsVidForVolumeClass(): void
    {
        $qb = $this->makeQbForIdentifiers(Volume::class, [['vid' => 1], ['vid' => 3]]);
        $result = $this->subject->getIdentifiers($qb, []);
        $this->assertSame([1, 3], $result);
    }

    public function testGetIdentifiersReturnsSidForSectionClass(): void
    {
        $qb = $this->makeQbForIdentifiers(Section::class, [['sid' => 5], ['sid' => 7]]);
        $result = $this->subject->getIdentifiers($qb, []);
        $this->assertSame([5, 7], $result);
    }

    public function testGetIdentifiersWithRvidFilterAddsWhereClause(): void
    {
        $capturedConditions = [];

        $ormQuery = $this->createMock(AbstractQuery::class);
        $ormQuery->method('getArrayResult')->willReturn([['vid' => 2]]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn([Volume::class]);
        $qb->method('getRootAliases')->willReturn(['v']);
        $qb->method('select')->willReturnSelf();
        $qb->method('andWhere')->willReturnCallback(
            function (string $cond) use ($qb, &$capturedConditions): QueryBuilder {
                $capturedConditions[] = $cond;
                return $qb;
            }
        );
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($ormQuery);
        $qb->method('expr')->willReturn(new Expr());

        $result = $this->subject->getIdentifiers($qb, ['rvid' => 7]);

        $this->assertSame([2], $result);
        $merged = implode(' ', $capturedConditions);
        $this->assertStringContainsString('rvId', $merged);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function createMockQueryBuilder(string $rootEntity): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn([$rootEntity]);
        $qb->method('getRootAliases')->willReturn(['alias']);
        return $qb;
    }
}
