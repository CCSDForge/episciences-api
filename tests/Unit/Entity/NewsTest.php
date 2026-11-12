<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\News;
use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class NewsTest extends TestCase
{
    private News $entity;

    protected function setUp(): void
    {
        $this->entity = new News();
    }

    public function testIsPublicByDefault(): void
    {
        $this->assertSame([News::VISIBILITY_PUBLIC], $this->entity->getVisibility());
        $this->assertTrue($this->entity->isPublic());
    }

    public function testPrivateNewsIsNotPublic(): void
    {
        $this->entity->setVisibility(News::VISIBILITY_PRIVATE);

        $this->assertSame([News::VISIBILITY_PRIVATE], $this->entity->getVisibility());
        $this->assertFalse($this->entity->isPublic());
    }

    public function testNullVisibilityIsNotPublic(): void
    {
        $this->entity->setVisibility(null);

        $this->assertSame([], $this->entity->getVisibility());
        $this->assertFalse($this->entity->isPublic());
    }

    public function testVisibilityIsMappedToTheEnumColumnOfEpisciences(): void
    {
        $column = new ReflectionProperty(News::class, 'visibility')->getAttributes(Column::class)[0]->newInstance();

        $this->assertSame('visibility_enum', $column->name);
    }

    /**
     * The creator is public: it is embedded without IRI (screen name only).
     */
    public function testCreatorIsEmbeddedWithoutIri(): void
    {
        $attributes = new ReflectionProperty(News::class, 'creator')->getAttributes(ApiProperty::class);

        $this->assertCount(1, $attributes);
        $this->assertFalse($attributes[0]->newInstance()->getGenId());
    }
}
