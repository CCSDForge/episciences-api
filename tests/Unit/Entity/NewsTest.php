<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\News;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class NewsTest extends TestCase
{
    private News $entity;

    protected function setUp(): void
    {
        $this->entity = new News();
    }

    public function testIsPublicDefaultsToNull(): void
    {
        $this->assertNull($this->entity->isPublic());
    }

    /**
     * `is_public` is a MySQL STORED GENERATED column (insertable/updatable: false):
     * it has no setter and is only ever populated by Doctrine when hydrating a row.
     */
    public function testIsPublicReflectsHydratedGeneratedColumnValue(): void
    {
        $property = new ReflectionProperty(News::class, 'is_public');
        $property->setValue($this->entity, true);

        $this->assertTrue($this->entity->isPublic());
    }

    public function testIsPublicReflectsHydratedFalseValue(): void
    {
        $property = new ReflectionProperty(News::class, 'is_public');
        $property->setValue($this->entity, false);

        $this->assertFalse($this->entity->isPublic());
    }
}
