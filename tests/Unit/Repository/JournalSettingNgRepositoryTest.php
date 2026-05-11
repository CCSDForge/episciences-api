<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\JournalSettingNg;
use App\Repository\JournalSettingNgRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JournalSettingNgRepositoryTest extends TestCase
{
    private JournalSettingNgRepository $repository;
    private MockObject $entityManager;
    private MockObject $queryBuilder;
    private MockObject $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(AbstractQuery::class);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = JournalSettingNg::class;

        $this->entityManager
            ->method('getClassMetadata')
            ->with(JournalSettingNg::class)
            ->willReturn($classMetadata);

        $this->entityManager
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        // EntityRepository::createQueryBuilder('fs') calls select('fs') then from(...) internally
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
        $this->queryBuilder->method('getRootAliases')->willReturn(['fs']);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $this->repository = new JournalSettingNgRepository($registry);
    }

    public function testGetFrontSettingsFiltersOnRvid(): void
    {
        $rvId = 42;

        $this->queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('fs.rvid= :rvId')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('rvId', $rvId)
            ->willReturnSelf();

        $this->query
            ->method('getOneOrNullResult')
            ->with(AbstractQuery::HYDRATE_SCALAR_COLUMN)
            ->willReturn(['menu' => ['authorsRender' => true]]);

        $result = $this->repository->getFrontSettings($rvId);

        $this->assertSame(['menu' => ['authorsRender' => true]], $result);
    }

    public function testGetFrontSettingsReturnsNullWhenNoRecord(): void
    {
        $this->query
            ->method('getOneOrNullResult')
            ->with(AbstractQuery::HYDRATE_SCALAR_COLUMN)
            ->willReturn(null);

        $result = $this->repository->getFrontSettings(999);

        $this->assertNull($result);
    }

    public function testGetFrontSettingsPropagatesNonUniqueResultException(): void
    {
        $this->query
            ->method('getOneOrNullResult')
            ->willThrowException(new NonUniqueResultException());

        $this->expectException(NonUniqueResultException::class);

        $this->repository->getFrontSettings(1);
    }
}