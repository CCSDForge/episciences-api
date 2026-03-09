<?php

namespace App\Tests\Unit\Service;

use App\Entity\MetadataSources as MetadataSourcesEntity;
use App\Repository\MetadataSourcesRepository;
use App\Service\MetadataSources;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MetadataSourcesTest extends TestCase
{
    private MockObject|MetadataSourcesRepository $repository;
    private MetadataSources $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MetadataSourcesRepository::class);
        $this->service = new MetadataSources($this->repository);
    }

    // --- getRepositories() ---

    public function testGetRepositoriesLazyLoadsFromDatabase(): void
    {
        $repo1 = $this->createMock(MetadataSourcesEntity::class);
        $repo2 = $this->createMock(MetadataSourcesEntity::class);

        $this->repository
            ->expects($this->once()) // must query DB only once (lazy load)
            ->method('findAll')
            ->willReturn([$repo1, $repo2]);

        $result = $this->service->getRepositories();

        $this->assertCount(2, $result);
    }

    public function testGetRepositoriesCachesResultOnSecondCall(): void
    {
        $repo = $this->createMock(MetadataSourcesEntity::class);

        $this->repository
            ->expects($this->once()) // DB called only once despite two calls
            ->method('findAll')
            ->willReturn([$repo]);

        $this->service->getRepositories();
        $this->service->getRepositories(); // second call must hit the cache
    }

    public function testGetRepositoriesReturnsEmptyArrayWhenNoneFound(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $result = $this->service->getRepositories();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // --- loadRepositories() ---

    public function testLoadRepositoriesRefreshesCache(): void
    {
        $repo1 = $this->createMock(MetadataSourcesEntity::class);
        $repo2 = $this->createMock(MetadataSourcesEntity::class);

        $this->repository
            ->expects($this->exactly(2))
            ->method('findAll')
            ->willReturn([$repo1, $repo2]);

        $this->service->loadRepositories();
        $this->service->loadRepositories(); // explicit reload must hit DB again
    }

    // --- getLabel() ---

    public function testGetLabelReturnsNameForKnownRepoId(): void
    {
        $repoEntity = $this->createMock(MetadataSourcesEntity::class);
        $repoEntity->method('getName')->willReturn('HAL');

        $this->repository->method('findAll')->willReturn([1 => $repoEntity]);

        $label = $this->service->getLabel(1);

        $this->assertSame('HAL', $label);
    }

    public function testGetLabelThrowsForUnknownRepoId(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('42');

        $this->service->getLabel(42);
    }

    public function testGetLabelThrowsWithDescriptiveMessageContainingId(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        try {
            $this->service->getLabel(99);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('99', $e->getMessage());
        }
    }

    // --- repositoryToArray() ---

    public function testRepositoryToArrayDelegatesToEntity(): void
    {
        $repoEntity = $this->createMock(MetadataSourcesEntity::class);
        $repoEntity->method('toArray')->willReturn(['id' => 3, 'name' => 'arXiv']);

        $this->repository->method('findAll')->willReturn([3 => $repoEntity]);

        $result = $this->service->repositoryToArray(3);

        $this->assertSame(['id' => 3, 'name' => 'arXiv'], $result);
    }

    public function testRepositoryToArrayThrowsForUnknownId(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->repositoryToArray(7);
    }
}
