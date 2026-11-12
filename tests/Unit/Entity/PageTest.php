<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Page;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PageTest extends TestCase
{
    private Page $entity;

    protected function setUp(): void
    {
        $this->entity = new Page();
    }

    public function testIsPublicByDefault(): void
    {
        $this->assertSame([Page::VISIBILITY_PUBLIC], $this->entity->getVisibility());
        $this->assertTrue($this->entity->isPublic());
    }

    public function testEmptyVisibilityIsNotPublic(): void
    {
        $this->entity->setVisibility([]);

        $this->assertSame([], $this->entity->getVisibility());
        $this->assertFalse($this->entity->isPublic());
    }

    public function testIsNotPublicWhenVisibilityHoldsPublicAmongRoles(): void
    {
        $this->entity->setVisibility(['public', 'member']);

        $this->assertSame(['public', 'member'], $this->entity->getVisibility());
        $this->assertFalse($this->entity->isPublic());
    }

    public function testIsNotPublicWhenRestrictedToRoles(): void
    {
        $this->entity->setVisibility(['chief_editor', 'administrator']);

        $this->assertSame(['chief_editor', 'administrator'], $this->entity->getVisibility());
        $this->assertFalse($this->entity->isPublic());
    }

    public function testVisibilityIsMappedToTheSetColumnOfEpisciences(): void
    {
        $column = new ReflectionProperty(Page::class, 'visibility')->getAttributes(Column::class)[0]->newInstance();

        $this->assertSame('visibility_set', $column->name);
        $this->assertSame(Types::SIMPLE_ARRAY, $column->type);
    }
}
