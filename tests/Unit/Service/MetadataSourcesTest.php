<?php

namespace App\Tests\Unit\Service;

use App\Entity\MetadataSources as MetadataSourcesEntity;
use App\Repository\MetadataSourcesRepository;
use App\Service\MetadataSources;
use PHPUnit\Framework\TestCase;

class MetadataSourcesTest extends TestCase
{
    private $repository;
    private $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MetadataSourcesRepository::class);
        $this->service = new MetadataSources($this->repository);
    }

    public function testGetLabelReturnsCorrectName(): void
    {
        $entity = $this->createMock(MetadataSourcesEntity::class);
        $entity->method('getName')->willReturn('HAL');

        $this->repository->method('findAll')->willReturn([
            1 => $entity
        ]);

        $this->assertEquals('HAL', $this->service->getLabel(1));
    }

    public function testGetLabelThrowsExceptionWhenNotFound(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->getLabel(99);
    }

    public function testRepositoryToArrayReturnsArray(): void
    {
        $entity = $this->createMock(MetadataSourcesEntity::class);
        $entity->method('toArray')->willReturn(['id' => 1, 'name' => 'HAL']);

        $this->repository->method('findAll')->willReturn([
            1 => $entity
        ]);

        $result = $this->service->repositoryToArray(1);
        $this->assertIsArray($result);
        $this->assertEquals('HAL', $result['name']);
    }
}
