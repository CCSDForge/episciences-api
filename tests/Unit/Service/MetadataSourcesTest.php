<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MetadataSources as MetadataSourcesEntity;
use App\Repository\MetadataSourcesRepository;
use App\Service\MetadataSources;
use PHPUnit\Framework\TestCase;

final class MetadataSourcesTest extends TestCase
{
    private MetadataSourcesRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MetadataSourcesRepository::class);
    }

    private function makeService(): MetadataSources
    {
        return new MetadataSources($this->repository);
    }

    private function makeEntity(int $id, string $name): MetadataSourcesEntity
    {
        $entity = $this->createMock(MetadataSourcesEntity::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('getName')->willReturn($name);
        $entity->method('toArray')->willReturn(['id' => $id, 'name' => $name]);
        return $entity;
    }

    // ── getRepositories: lazy loading ─────────────────────────────────────────

    public function testGetRepositoriesLoadsFromDbOnFirstCall(): void
    {
        $entities = [$this->makeEntity(1, 'HAL'), $this->makeEntity(2, 'arXiv')];

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($entities);

        $service = $this->makeService();
        $result = $service->getRepositories();

        $this->assertSame($entities, $result);
    }

    public function testGetRepositoriesCachesResultOnSecondCall(): void
    {
        $entities = [$this->makeEntity(1, 'HAL')];

        // findAll must be called exactly once despite two calls to getRepositories()
        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($entities);

        $service = $this->makeService();
        $service->getRepositories();
        $service->getRepositories();
    }

    // ── loadRepositories reloads from DB ─────────────────────────────────────

    public function testLoadRepositoriesAlwaysQueriesDb(): void
    {
        $entities = [$this->makeEntity(1, 'HAL')];

        $this->repository->expects($this->exactly(2))
            ->method('findAll')
            ->willReturn($entities);

        $service = $this->makeService();
        $service->loadRepositories();
        $service->loadRepositories(); // second explicit reload
    }

    // ── getLabel ──────────────────────────────────────────────────────────────

    public function testGetLabelReturnsEntityName(): void
    {
        $entities = [1 => $this->makeEntity(1, 'HAL'), 2 => $this->makeEntity(2, 'arXiv')];

        $this->repository->method('findAll')->willReturn($entities);

        $service = $this->makeService();

        $this->assertSame('HAL', $service->getLabel(1));
        $this->assertSame('arXiv', $service->getLabel(2));
    }

    // ── repositoryToArray ─────────────────────────────────────────────────────

    public function testRepositoryToArrayReturnsEntityArray(): void
    {
        $entity = $this->makeEntity(3, 'Zenodo');

        $this->repository->method('findAll')->willReturn([3 => $entity]);

        $service = $this->makeService();
        $result = $service->repositoryToArray(3);

        $this->assertIsArray($result);
        $this->assertSame(3, $result['id']);
        $this->assertSame('Zenodo', $result['name']);
    }
}
