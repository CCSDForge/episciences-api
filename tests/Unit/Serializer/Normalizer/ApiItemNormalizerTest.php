<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Entity\Section;
use App\Repository\PapersRepository;
use App\Repository\SectionRepository;
use App\Serializer\Normalizer\ApiItemNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Unit tests for ApiItemNormalizer.
 *
 * Covers:
 * - When $object is NOT an AbstractVolumeSection: decorated result is returned unchanged.
 * - When $object IS an AbstractVolumeSection implementing EntityIdentifierInterface:
 *   committee and totalPublishedArticles are fetched and appended to the data array.
 * - supportsNormalization() delegates to $decorated.
 * - getSupportedTypes() delegates to $decorated.
 * - setSerializer() propagates to $decorated if it implements SerializerAwareInterface.
 */
final class ApiItemNormalizerTest extends TestCase
{
    private MockObject|NormalizerInterface $decorated;
    private MockObject|EntityManagerInterface $entityManager;
    private ApiItemNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(NormalizerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->normalizer = new ApiItemNormalizer($this->decorated, $this->entityManager);
    }

    // ── Non-AbstractVolumeSection object ──────────────────────────────────────

    public function testNormalizeNonVolumeSectionReturnsDecoratedResult(): void
    {
        $object = new \stdClass();
        $this->decorated
            ->method('normalize')
            ->willReturn(['some' => 'data']);

        $result = $this->normalizer->normalize($object, 'json', []);

        $this->assertSame(['some' => 'data'], $result);
    }

    public function testNormalizeNonVolumeSectionDoesNotCallEntityManager(): void
    {
        $object = new \stdClass();
        $this->decorated->method('normalize')->willReturn([]);

        $this->entityManager->expects($this->never())->method('getRepository');

        $this->normalizer->normalize($object, 'json', []);
    }

    public function testNormalizeWithStringReturnFromDecoratedPassesThroughUnmodified(): void
    {
        $this->decorated->method('normalize')->willReturn('plain string');

        $result = $this->normalizer->normalize(new \stdClass(), null, []);
        $this->assertSame('plain string', $result);
    }

    // ── AbstractVolumeSection (Section) with non-array decorated result ────────

    public function testNormalizeVolumeSectionWithNonArrayDecoratedResultSkipsEnrichment(): void
    {
        $section = new Section();
        $section->setRvid(1)->setSid(1);

        // Decorated returns a string (not array) → enrichment is skipped
        $this->decorated->method('normalize')->willReturn('not-an-array');
        $this->entityManager->expects($this->never())->method('getRepository');

        $result = $this->normalizer->normalize($section, 'json', []);
        $this->assertSame('not-an-array', $result);
    }

    // ── AbstractVolumeSection (Section) with array decorated result ──────────

    public function testNormalizeVolumeSectionEnrichesDataWithCommittee(): void
    {
        $section = new Section();
        $section->setRvid(5)->setSid(10);

        $committee = [['uid' => 1, 'screenName' => 'editor1']];

        $sectionRepo = $this->createMock(SectionRepository::class);
        $sectionRepo->method('getCommittee')->with(5, 10)->willReturn($committee);

        $this->entityManager
            ->method('getRepository')
            ->with(Section::class)
            ->willReturn($sectionRepo);

        $this->decorated->method('normalize')->willReturn(['sid' => 10]);

        $result = $this->normalizer->normalize($section, 'json', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('committee', $result);
        $this->assertSame($committee, $result['committee']);
    }

    public function testNormalizeVolumeSectionEnrichesDataWithTotalPublishedArticles(): void
    {
        $section = new Section();
        $section->setRvid(5)->setSid(10);

        $sectionRepo = $this->createMock(SectionRepository::class);
        $sectionRepo->method('getCommittee')->willReturn([]);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($sectionRepo);

        $this->decorated->method('normalize')->willReturn(['sid' => 10]);

        $result = $this->normalizer->normalize($section, 'json', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(PapersRepository::TOTAL_ARTICLE, $result);
        // No papers added → total = 0
        $this->assertSame(0, $result[PapersRepository::TOTAL_ARTICLE]);
    }

    public function testNormalizeVolumeSectionPreservesExistingDecoratedData(): void
    {
        $section = new Section();
        $section->setRvid(3)->setSid(7);

        $sectionRepo = $this->createMock(SectionRepository::class);
        $sectionRepo->method('getCommittee')->willReturn([]);

        $this->entityManager->method('getRepository')->willReturn($sectionRepo);
        $this->decorated->method('normalize')->willReturn(['sid' => 7, 'name' => 'My Section']);

        $result = $this->normalizer->normalize($section, 'json', []);

        $this->assertSame(7, $result['sid']);
        $this->assertSame('My Section', $result['name']);
    }

    // ── supportsNormalization() ───────────────────────────────────────────────

    public function testSupportsNormalizationDelegatesToDecorated(): void
    {
        $this->decorated
            ->method('supportsNormalization')
            ->with('data', 'json', ['context'])
            ->willReturn(true);

        $this->assertTrue($this->normalizer->supportsNormalization('data', 'json', ['context']));
    }

    public function testSupportsNormalizationReturnsFalseWhenDecoratedReturnsFalse(): void
    {
        $this->decorated->method('supportsNormalization')->willReturn(false);
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, []));
    }

    // ── getSupportedTypes() ───────────────────────────────────────────────────

    public function testGetSupportedTypesDelegatesToDecorated(): void
    {
        $types = [Section::class => true, '*' => false];
        $this->decorated->method('getSupportedTypes')->with('json')->willReturn($types);

        $this->assertSame($types, $this->normalizer->getSupportedTypes('json'));
    }

    // ── setSerializer() ───────────────────────────────────────────────────────

    public function testSetSerializerPropagatesWhenDecoratedImplementsSerializerAwareInterface(): void
    {
        // Need an object implementing both NormalizerInterface and SerializerAwareInterface.
        // We use getMockBuilder on a concrete anonymous class that extends both.
        $serializerCalled = false;
        $capturedSerializer = null;

        $decorated = new class ($serializerCalled, $capturedSerializer) implements NormalizerInterface, SerializerAwareInterface {
            public function __construct(private bool &$called, private mixed &$captured) {}
            public function normalize(mixed $data, ?string $format = null, array $context = []): array|\ArrayObject|string|int|float|bool|null { return []; }
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool { return true; }
            public function getSupportedTypes(?string $format): array { return []; }
            public function setSerializer(SerializerInterface $serializer): void {
                $this->called = true;
                $this->captured = $serializer;
            }
        };

        $serializer = $this->createMock(SerializerInterface::class);
        $normalizer = new ApiItemNormalizer($decorated, $this->entityManager);
        $normalizer->setSerializer($serializer);

        $this->assertTrue($serializerCalled);
        $this->assertSame($serializer, $capturedSerializer);
    }

    public function testSetSerializerDoesNotFailWhenDecoratedIsNotSerializerAware(): void
    {
        // $this->decorated is NormalizerInterface only (no SerializerAwareInterface)
        $serializer = $this->createMock(SerializerInterface::class);
        // Should not throw
        $this->normalizer->setSerializer($serializer);
        $this->assertTrue(true); // No assertion needed — just verify no exception
    }
}
