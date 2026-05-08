<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\JournalSettingNg;
use PHPUnit\Framework\TestCase;

class JournalSettingNgTest extends TestCase
{
    private JournalSettingNg $entity;

    protected function setUp(): void
    {
        $this->entity = new JournalSettingNg();
    }

    public function testTableConstant(): void
    {
        $this->assertSame('JOURNAL_SETTING', JournalSettingNg::TABLE);
    }

    public function testSetAndGetId(): void
    {
        $result = $this->entity->setId(42);

        $this->assertSame($this->entity, $result);
        $this->assertSame(42, $this->entity->getId());
    }

    public function testSetAndGetRvid(): void
    {
        $result = $this->entity->setRvid(7);

        $this->assertSame($this->entity, $result);
        $this->assertSame(7, $this->entity->getRvid());
    }

    public function testSetRvidOverwritesPreviousValue(): void
    {
        $this->entity->setRvid(10);
        $this->entity->setRvid(20);

        $this->assertSame(20, $this->entity->getRvid());
    }

    public function testSetAndGetSettings(): void
    {
        $settings = ['menu' => ['authorsRender' => true], 'theme' => ['primaryColor' => '#49737e']];

        $result = $this->entity->setSettings($settings);

        $this->assertSame($this->entity, $result);
        $this->assertSame($settings, $this->entity->getSettings());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTime('2024-01-15 10:00:00');

        $result = $this->entity->setCreatedAt($date);

        $this->assertSame($this->entity, $result);
        $this->assertSame($date, $this->entity->getCreatedAt());
    }

    public function testCreatedAtIsNullable(): void
    {
        $this->entity->setCreatedAt(new \DateTime());
        $result = $this->entity->setCreatedAt(null);

        $this->assertSame($this->entity, $result);
        $this->assertNull($this->entity->getCreatedAt());
    }

    public function testSetAndGetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-01 08:30:00');

        $result = $this->entity->setUpdatedAt($date);

        $this->assertSame($this->entity, $result);
        $this->assertSame($date, $this->entity->getUpdatedAt());
    }

    public function testUpdatedAtIsNullable(): void
    {
        $this->entity->setUpdatedAt(new \DateTime());
        $result = $this->entity->setUpdatedAt(null);

        $this->assertSame($this->entity, $result);
        $this->assertNull($this->entity->getUpdatedAt());
    }

    public function testFluentChaining(): void
    {
        $settings = ['key' => 'value'];
        $date = new \DateTime();

        $result = $this->entity
            ->setId(1)
            ->setRvid(5)
            ->setSettings($settings)
            ->setCreatedAt($date)
            ->setUpdatedAt($date);

        $this->assertSame($this->entity, $result);
        $this->assertSame(1, $this->entity->getId());
        $this->assertSame(5, $this->entity->getRvid());
        $this->assertSame($settings, $this->entity->getSettings());
        $this->assertSame($date, $this->entity->getCreatedAt());
        $this->assertSame($date, $this->entity->getUpdatedAt());
    }
}