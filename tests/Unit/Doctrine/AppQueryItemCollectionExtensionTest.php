<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\AppConstants;
use App\Doctrine\AppQueryItemCollectionExtension;
use App\Entity\News;
use App\Entity\Page;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The pages and news are restricted to the public ones: their `visibility_set` (pages) or
 * `visibility_enum` (news) column of Episciences is exactly "public".
 */
class AppQueryItemCollectionExtensionTest extends TestCase
{
    /** @var Security&MockObject */
    private MockObject $security;

    /** @var QueryNameGeneratorInterface&MockObject */
    private MockObject $queryNameGenerator;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->security->method('getUser')->willReturn(null);

        $this->queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
    }

    public function testApplyToItemFiltersPagesByVisibilitySet(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['p0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('p0_.visibility = :publicVisibility')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('publicVisibility', Page::VISIBILITY_PUBLIC)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, Page::class, ['id' => 1]);
    }

    public function testApplyToItemFiltersNewsByVisibilityEnum(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('n0_.visibility = :publicVisibility')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('publicVisibility', News::VISIBILITY_PUBLIC)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, News::class, ['id' => 1]);
    }

    public function testApplyToCollectionFiltersNewsByVisibilityEnumWithoutRvCodeFilter(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('n0_.visibility = :publicVisibility')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('publicVisibility', News::VISIBILITY_PUBLIC)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToCollection($queryBuilder, $this->queryNameGenerator, News::class);
    }

    public function testApplyToItemCombinesYearFilterWithVisibilityForNews(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $andWhereConditions = [];
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) use ($queryBuilder, &$andWhereConditions): \PHPUnit\Framework\MockObject\MockObject {
                $andWhereConditions[] = $condition;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('publicVisibility', News::VISIBILITY_PUBLIC)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $context = ['filters' => [AppConstants::YEAR_PARAM => '2024']];

        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, News::class, ['id' => 1], null, $context);

        // The year filter is applied first, still as a dedicated OR expression...
        $this->assertInstanceOf(Orx::class, $andWhereConditions[0]);
        // ...and the visibility check is appended last, unchanged.
        $this->assertSame('n0_.visibility = :publicVisibility', $andWhereConditions[1]);
    }

    public function testApplyToItemDoesNotFilterByVisibilityForUnrelatedResource(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['r0_']);

        $queryBuilder->expects($this->never())->method('setParameter')->with('publicVisibility', $this->anything());

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, stdClass::class, ['id' => 1]);
    }
}
