<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MailTemplate;
use App\Entity\PaperRatingGrid;
use App\Entity\ReviewerAlias;
use App\Entity\ReviewerPool;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for remaining simple entities that don't have dedicated test files.
 *
 * Covers: MailTemplate (full setters/getters), PaperRatingGrid, ReviewerAlias,
 * ReviewerPool (read-only ID entities).
 */
final class RemainingEntitiesTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════════
    // MailTemplate
    // ══════════════════════════════════════════════════════════════════════════

    public function testMailTemplateDefaultParentidIsNull(): void
    {
        $entity = new MailTemplate();
        $this->assertNull($entity->getParentid());
    }

    public function testMailTemplateDefaultRvidIsNull(): void
    {
        $entity = new MailTemplate();
        $this->assertNull($entity->getRvid());
    }

    public function testMailTemplateDefaultRvcodeIsNull(): void
    {
        $entity = new MailTemplate();
        $this->assertNull($entity->getRvcode());
    }

    public function testMailTemplateDefaultPositionIsNull(): void
    {
        $entity = new MailTemplate();
        $this->assertNull($entity->getPosition());
    }

    public function testMailTemplateSetParentidReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $result = $entity->setParentid(5);
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetParentidReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setParentid(42);
        $this->assertSame(42, $entity->getParentid());
    }

    public function testMailTemplateSetParentidWithNullResetsToNull(): void
    {
        $entity = new MailTemplate();
        $entity->setParentid(10);
        $entity->setParentid(null);
        $this->assertNull($entity->getParentid());
    }

    public function testMailTemplateSetRvidReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $result = $entity->setRvid(7);
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetRvidReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setRvid(99);
        $this->assertSame(99, $entity->getRvid());
    }

    public function testMailTemplateSetRvcodeReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $result = $entity->setRvcode('epijinfo');
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetRvcodeReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setRvcode('myjournal');
        $this->assertSame('myjournal', $entity->getRvcode());
    }

    public function testMailTemplateSetRvcodeWithNullResetsToNull(): void
    {
        $entity = new MailTemplate();
        $entity->setRvcode('test');
        $entity->setRvcode(null);
        $this->assertNull($entity->getRvcode());
    }

    public function testMailTemplateSetKeyReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $result = $entity->setKey('submission_received');
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetKeyReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setKey('review_request');
        $this->assertSame('review_request', $entity->getKey());
    }

    public function testMailTemplateSetTypeReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $entity->setKey('k'); // key must be set first (non-nullable)
        $result = $entity->setType('html');
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetTypeReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setKey('k');
        $entity->setType('text');
        $this->assertSame('text', $entity->getType());
    }

    public function testMailTemplateSetPositionReturnsSelf(): void
    {
        $entity = new MailTemplate();
        $result = $entity->setPosition(3);
        $this->assertSame($entity, $result);
    }

    public function testMailTemplateGetPositionReturnsSetValue(): void
    {
        $entity = new MailTemplate();
        $entity->setPosition(10);
        $this->assertSame(10, $entity->getPosition());
    }

    public function testMailTemplateSetPositionWithNullResetsToNull(): void
    {
        $entity = new MailTemplate();
        $entity->setPosition(1);
        $entity->setPosition(null);
        $this->assertNull($entity->getPosition());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PaperRatingGrid — read-only compound PK (docid + rgid)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * PaperRatingGrid has no setters — properties are initialized by Doctrine.
     * We verify that the class can be instantiated and getters exist.
     */
    public function testPaperRatingGridCanBeInstantiated(): void
    {
        $entity = new PaperRatingGrid();
        $this->assertInstanceOf(PaperRatingGrid::class, $entity);
    }

    public function testPaperRatingGridGettersReturnNullableInt(): void
    {
        // Use reflection to initialize the private typed properties without setter
        $entity = new PaperRatingGrid();
        $ref = new \ReflectionClass($entity);

        $docidProp = $ref->getProperty('docid');
        $docidProp->setValue($entity, 42);

        $rgidProp = $ref->getProperty('rgid');
        $rgidProp->setValue($entity, 7);

        $this->assertSame(42, $entity->getDocid());
        $this->assertSame(7, $entity->getRgid());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ReviewerAlias — read-only compound PK (uid + docid + alias)
    // ══════════════════════════════════════════════════════════════════════════

    public function testReviewerAliasCanBeInstantiated(): void
    {
        $entity = new ReviewerAlias();
        $this->assertInstanceOf(ReviewerAlias::class, $entity);
    }

    public function testReviewerAliasGettersReturnExpectedValues(): void
    {
        $entity = new ReviewerAlias();
        $ref = new \ReflectionClass($entity);

        $ref->getProperty('uid')->setValue($entity, 10);
        $ref->getProperty('docid')->setValue($entity, 20);
        $ref->getProperty('alias')->setValue($entity, 30);

        $this->assertSame(10, $entity->getUid());
        $this->assertSame(20, $entity->getDocid());
        $this->assertSame(30, $entity->getAlias());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ReviewerPool — read-only compound PK (rvid + vid + uid)
    // ══════════════════════════════════════════════════════════════════════════

    public function testReviewerPoolCanBeInstantiated(): void
    {
        $entity = new ReviewerPool();
        $this->assertInstanceOf(ReviewerPool::class, $entity);
    }

    public function testReviewerPoolGettersReturnExpectedValues(): void
    {
        $entity = new ReviewerPool();
        $ref = new \ReflectionClass($entity);

        $ref->getProperty('rvid')->setValue($entity, 1);
        $ref->getProperty('vid')->setValue($entity, 2);
        $ref->getProperty('uid')->setValue($entity, 3);

        $this->assertSame(1, $entity->getRvid());
        $this->assertSame(2, $entity->getVid());
        $this->assertSame(3, $entity->getUid());
    }
}
