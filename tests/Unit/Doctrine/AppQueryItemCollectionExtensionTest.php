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
 * Regression coverage for the fix that replaced the non-indexed
 * JSON_EXTRACT(visibility, '$[0]') = 'public' clause (source of the
 * "Out of sort memory" 500 on GET /api/pages and GET /api/news) with a
 * comparison on the indexed, MySQL-maintained `is_public` generated column.
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

    public function testApplyToItemFiltersPagesByIsPublicColumn(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['p0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('p0_.is_public = :isPublic')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('isPublic', true)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, Page::class, ['id' => 1]);
    }

    public function testApplyToItemFiltersNewsByIsPublicColumn(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('n0_.is_public = :isPublic')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('isPublic', true)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, News::class, ['id' => 1]);
    }

    public function testApplyToCollectionFiltersNewsByIsPublicColumnWithoutRvCodeFilter(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('n0_.is_public = :isPublic')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('isPublic', true)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToCollection($queryBuilder, $this->queryNameGenerator, News::class);
    }

    public function testApplyToItemCombinesYearFilterWithIsPublicColumnForNews(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['n0_']);
        $queryBuilder->method('expr')->willReturn(new Expr());

        $andWhereConditions = [];
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) use ($queryBuilder, &$andWhereConditions) {
                $andWhereConditions[] = $condition;

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('isPublic', true)
            ->willReturnSelf();

        $extension = new AppQueryItemCollectionExtension($this->security);
        $context = ['filters' => [AppConstants::YEAR_PARAM => '2024']];

        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, News::class, ['id' => 1], null, $context);

        // The year filter is applied first, still as a dedicated OR expression...
        $this->assertInstanceOf(Orx::class, $andWhereConditions[0]);
        // ...and the is_public column check is appended last, unchanged.
        $this->assertSame('n0_.is_public = :isPublic', $andWhereConditions[1]);
    }

    public function testApplyToItemDoesNotFilterByIsPublicColumnForUnrelatedResource(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['r0_']);

        $queryBuilder->expects($this->never())->method('setParameter')->with('isPublic', $this->anything());

        $extension = new AppQueryItemCollectionExtension($this->security);
        $extension->applyToItem($queryBuilder, $this->queryNameGenerator, stdClass::class, ['id' => 1]);
    }
}
