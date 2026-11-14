<?php

namespace App\Tests\Unit\Service;

use App\Entity\MetadataSources as MetadataSourcesEntity;
use App\Repository\MetadataSourcesRepository;
use App\Service\MetadataSources;
use PHPUnit\Framework\TestCase;

class MetadataSourcesTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $repository;
    private \App\Service\MetadataSources $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MetadataSourcesRepository::class);
        $this->service = new MetadataSources($this->repository);
    }

    public function testGetLabelReturnsCorrectName(): void
    {
        $entity = $this->createMock(MetadataSourcesEntity::class);
        $entity->method('getId')->willReturn(1);
        $entity->method('getName')->willReturn('HAL');
        $entity->method('getType')->willReturn(MetadataSources::TYPE_PAPERS_REPOSITORY);

        $this->repository->method('findAll')->willReturn([$entity]);

        $this->assertEquals('HAL', $this->service->getLabel(1));
    }

    public function testGetLabelReturnsEmptyStringWhenNotFound(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $this->assertEquals('', $this->service->getLabel(99));
    }

    public function testGetRepositoriesExcludesNonPaperRepositoryTypes(): void
    {
        $papers = $this->createMock(MetadataSourcesEntity::class);
        $papers->method('getId')->willReturn(1);
        $papers->method('getName')->willReturn('A repository');
        $papers->method('getType')->willReturn(MetadataSources::TYPE_PAPERS_REPOSITORY);

        $metadata = $this->createMock(MetadataSourcesEntity::class);
        $metadata->method('getId')->willReturn(2);
        $metadata->method('getName')->willReturn('A metadata source');
        $metadata->method('getType')->willReturn(MetadataSources::TYPE_METADATA_REPOSITORY);

        $softwareHeritage = $this->createMock(MetadataSourcesEntity::class);
        $softwareHeritage->method('getId')->willReturn(3);
        $softwareHeritage->method('getName')->willReturn('Software Heritage');
        $softwareHeritage->method('getType')->willReturn(MetadataSources::TYPE_PAPERS_REPOSITORY);

        $this->repository->method('findAll')->willReturn([$papers, $metadata, $softwareHeritage]);

        $repositories = $this->service->getRepositories();

        $this->assertArrayHasKey(1, $repositories);
        $this->assertArrayNotHasKey(2, $repositories);
        $this->assertArrayNotHasKey(3, $repositories);
    }

    public function testRepositoryToArrayReturnsArray(): void
    {
        $entity = $this->createMock(MetadataSourcesEntity::class);
        $entity->method('getId')->willReturn(1);
        $entity->method('getName')->willReturn('HAL');
        $entity->method('getType')->willReturn(MetadataSources::TYPE_PAPERS_REPOSITORY);
        $entity->method('toArray')->willReturn(['id' => 1, 'name' => 'HAL']);

        $this->repository->method('findAll')->willReturn([$entity]);

        $result = $this->service->repositoryToArray(1);
        $this->assertIsArray($result);
        $this->assertEquals('HAL', $result['name']);
    }
}
