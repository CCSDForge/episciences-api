<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\MetadataSources;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MetadataSources entity.
 *
 * Covers all getters/setters and the toArray() method.
 */
final class MetadataSourcesEntityTest extends TestCase
{
    private MetadataSources $entity;

    protected function setUp(): void
    {
        $this->entity = new MetadataSources();
    }

    public function testTableConstant(): void
    {
        $this->assertSame('metadata_sources', MetadataSources::TABLE);
    }

    public function testDefaultStatusIsTrue(): void
    {
        $this->assertTrue($this->entity->isStatus());
    }

    public function testDefaultIdentifierIsNull(): void
    {
        $this->assertNull($this->entity->getIdentifier());
    }

    public function testDefaultBaseUrlIsNull(): void
    {
        $this->assertNull($this->entity->getBaseUrl());
    }

    public function testSetIdReturnsSelf(): void
    {
        $result = $this->entity->setId(1);
        $this->assertSame($this->entity, $result);
    }

    public function testGetIdReturnsSetValue(): void
    {
        $this->entity->setId(42);
        $this->assertSame(42, $this->entity->getId());
    }

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->entity->setName('HAL');
        $this->assertSame($this->entity, $result);
    }

    public function testGetNameReturnsSetValue(): void
    {
        $this->entity->setName('arXiv');
        $this->assertSame('arXiv', $this->entity->getName());
    }

    public function testSetTypeReturnsSelf(): void
    {
        $result = $this->entity->setType('oai');
        $this->assertSame($this->entity, $result);
    }

    public function testGetTypeReturnsSetValue(): void
    {
        $this->entity->setType('api');
        $this->assertSame('api', $this->entity->getType());
    }

    public function testSetStatusReturnsSelf(): void
    {
        $result = $this->entity->setStatus(false);
        $this->assertSame($this->entity, $result);
    }

    public function testIsStatusReturnsFalseWhenSetFalse(): void
    {
        $this->entity->setStatus(false);
        $this->assertFalse($this->entity->isStatus());
    }

    public function testSetIdentifierReturnsSelf(): void
    {
        $result = $this->entity->setIdentifier('oai:hal.science');
        $this->assertSame($this->entity, $result);
    }

    public function testGetIdentifierReturnsSetValue(): void
    {
        $this->entity->setIdentifier('oai:arxiv.org');
        $this->assertSame('oai:arxiv.org', $this->entity->getIdentifier());
    }

    public function testSetIdentifierWithNullResetsToNull(): void
    {
        $this->entity->setIdentifier('oai:example.org');
        $this->entity->setIdentifier(null);
        $this->assertNull($this->entity->getIdentifier());
    }

    public function testSetBaseUrlReturnsSelf(): void
    {
        $result = $this->entity->setBaseUrl('https://hal.science/oai/request');
        $this->assertSame($this->entity, $result);
    }

    public function testGetBaseUrlReturnsSetValue(): void
    {
        $this->entity->setBaseUrl('https://export.arxiv.org/oai2');
        $this->assertSame('https://export.arxiv.org/oai2', $this->entity->getBaseUrl());
    }

    public function testSetDoiPrefixReturnsSelf(): void
    {
        $result = $this->entity->setDoiPrefix('10.1234');
        $this->assertSame($this->entity, $result);
    }

    public function testGetDoiPrefixReturnsSetValue(): void
    {
        $this->entity->setDoiPrefix('10.48550');
        $this->assertSame('10.48550', $this->entity->getDoiPrefix());
    }

    public function testSetApiUrlReturnsSelf(): void
    {
        $result = $this->entity->setApiUrl('https://api.hal.science');
        $this->assertSame($this->entity, $result);
    }

    public function testGetApiUrlReturnsSetValue(): void
    {
        $this->entity->setApiUrl('https://export.arxiv.org/api');
        $this->assertSame('https://export.arxiv.org/api', $this->entity->getApiUrl());
    }

    public function testSetDocUrlReturnsSelf(): void
    {
        $result = $this->entity->setDocUrl('https://hal.science/v1/');
        $this->assertSame($this->entity, $result);
    }

    public function testGetDocUrlReturnsSetValue(): void
    {
        $this->entity->setDocUrl('https://arxiv.org/abs/');
        $this->assertSame('https://arxiv.org/abs/', $this->entity->getDocUrl());
    }

    public function testSetPaperUrlReturnsSelf(): void
    {
        $result = $this->entity->setPaperUrl('https://hal.science/v1/pdf/');
        $this->assertSame($this->entity, $result);
    }

    public function testGetPaperUrlReturnsSetValue(): void
    {
        $this->entity->setPaperUrl('https://arxiv.org/pdf/');
        $this->assertSame('https://arxiv.org/pdf/', $this->entity->getPaperUrl());
    }

    // ── toArray() ─────────────────────────────────────────────────────────────

    public function testToArrayContainsExpectedKeys(): void
    {
        $this->entity->setId(1);
        $this->entity->setName('HAL');
        $this->entity->setType('oai');
        $this->entity->setDoiPrefix('10.1234');
        $this->entity->setApiUrl('https://api.hal.science');
        $this->entity->setDocUrl('https://hal.science/v1/');
        $this->entity->setPaperUrl('https://hal.science/pdf/');

        $array = $this->entity->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('baseUrl', $array);
        $this->assertArrayHasKey('doiPrefix', $array);
        $this->assertArrayHasKey('apiUrl', $array);
    }

    public function testToArrayReturnsCorrectValues(): void
    {
        $this->entity->setId(5);
        $this->entity->setName('HAL');
        $this->entity->setType('oai');
        $this->entity->setBaseUrl('https://hal.science/oai');
        $this->entity->setDoiPrefix('10.1234');
        $this->entity->setApiUrl('https://api.hal.science');
        $this->entity->setDocUrl('https://hal.science/v1/');
        $this->entity->setPaperUrl('https://hal.science/pdf/');

        $array = $this->entity->toArray();

        $this->assertSame(5, $array['id']);
        $this->assertSame('HAL', $array['name']);
        $this->assertSame('oai', $array['type']);
        $this->assertSame('https://hal.science/oai', $array['baseUrl']);
        $this->assertSame('10.1234', $array['doiPrefix']);
        $this->assertSame('https://api.hal.science', $array['apiUrl']);
    }

    public function testToArrayBaseUrlIsNullWhenNotSet(): void
    {
        $this->entity->setId(1);
        $this->entity->setName('arXiv');
        $this->entity->setType('api');
        $this->entity->setDoiPrefix('10.48550');
        $this->entity->setApiUrl('https://export.arxiv.org/api');
        $this->entity->setDocUrl('https://arxiv.org/abs/');
        $this->entity->setPaperUrl('https://arxiv.org/pdf/');

        $array = $this->entity->toArray();
        $this->assertNull($array['baseUrl']);
    }
}
