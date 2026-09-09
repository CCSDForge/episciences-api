<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Page;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PageTest extends TestCase
{
    private Page $entity;

    protected function setUp(): void
    {
        $this->entity = new Page();
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
        $property = new ReflectionProperty(Page::class, 'is_public');
        $property->setValue($this->entity, true);

        $this->assertTrue($this->entity->isPublic());
    }

    public function testIsPublicReflectsHydratedFalseValue(): void
    {
        $property = new ReflectionProperty(Page::class, 'is_public');
        $property->setValue($this->entity, false);

        $this->assertFalse($this->entity->isPublic());
    }
}
