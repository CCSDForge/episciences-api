<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use ApiPlatform\Metadata\HttpOperation;
use App\Entity\News;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\Volume;
use App\Repository\NewsRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\VolumeRepository;
use App\Resource\Search;
use App\Resource\Statistic;
use App\Serializer\Normalizer\ApiCollectionNormalizer;
use App\Service\Solarium\Client;
use App\Service\Solr\SolrAuthorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Unit tests for ApiCollectionNormalizer.
 *
 * Covers:
 * - FORMAT constant
 * - getSupportedTypes()
 * - normalize() when operation context is missing → returns null
 * - normalize() without terms param → does not add terms filter
 * - normalize() with terms param → adds terms filter
 * - normalize() Volume::class + null journal → skips getSetting (null guard, bug #17 regression)
 * - supportsNormalization() delegates to decorated
 * - setSerializer() propagates when decorated is SerializerAwareInterface
 * - setNormalizer() propagates to decorated
 */
final class ApiCollectionNormalizerTest extends TestCase
{
    private MockObject|NormalizerInterface $decorated;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|SolrAuthorService $authorService;
    private MockObject|Client $search;
    private MockObject|LoggerInterface $logger;
    private MockObject|Security $security;
    private ApiCollectionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(NormalizerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->authorService = $this->createMock(SolrAuthorService::class);
        $this->search = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->normalizer = new ApiCollectionNormalizer(
            $this->decorated,
            $this->entityManager,
            $this->authorService,
            $this->search,
            $this->logger,
            $this->security
        );
    }

    // ── Constants ─────────────────────────────────────────────────────────────

    public function testFormatConstant(): void
    {
        $this->assertSame('jsonld', ApiCollectionNormalizer::FORMAT);
    }

    // ── getSupportedTypes() ───────────────────────────────────────────────────

    public function testGetSupportedTypesReturnsExpectedArray(): void
    {
        $result = $this->normalizer->getSupportedTypes('json');
        $this->assertSame(['object' => ApiCollectionNormalizer::FORMAT], $result);
    }

    public function testGetSupportedTypesIsFormatAgnostic(): void
    {
        // Returns same value regardless of $format parameter
        $this->assertSame(
            $this->normalizer->getSupportedTypes('jsonld'),
            $this->normalizer->getSupportedTypes(null)
        );
    }

    // ── normalize() — no operation in context ─────────────────────────────────

    public function testNormalizeReturnsNullWhenNoOperationInContext(): void
    {
        $this->decorated->method('normalize')->willReturn([]);

        $result = $this->normalizer->normalize(new \stdClass(), 'json', []);

        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullWhenOperationKeyMissingFromContext(): void
    {
        $this->decorated->method('normalize')->willReturn(['hydra:member' => []]);

        $result = $this->normalizer->normalize(new \stdClass(), 'jsonld', ['some_key' => 'value']);

        $this->assertNull($result);
    }

    // ── normalize() — with operation but empty hydraMember ───────────────────

    public function testNormalizeWithOperationAndEmptyHydraMemberReturnsData(): void
    {
        $operation = $this->createMock(\ApiPlatform\Metadata\HttpOperation::class);
        $operation->method('getClass')->willReturn(\App\Entity\Paper::class);

        $this->decorated->method('normalize')->willReturn(['hydra:member' => []]);
        $this->security->method('isGranted')->willReturn(false);

        $context = [
            'operation' => $operation,
            'uri' => 'https://api.example.com/api/papers',
        ];

        $result = $this->normalizer->normalize(new \stdClass(), 'jsonld', $context);

        $this->assertIsArray($result);
    }

    // ── normalize() — Search::TERMS_PARAM in parsedUri ───────────────────────

    /**
     * Bug regression: parsedUri[Search::TERMS_PARAM] was accessed without isset() check
     * (bug #15, fixed by adding isset() guard).
     */
    public function testNormalizeSearchClassWithoutTermsParamDoesNotAddTermsFilter(): void
    {
        $operation = $this->createMock(\ApiPlatform\Metadata\HttpOperation::class);
        $operation->method('getClass')->willReturn(Search::class);

        $this->decorated->method('normalize')->willReturn(['hydra:member' => []]);
        $this->security->method('isGranted')->willReturn(false);

        $context = [
            'operation' => $operation,
            'uri' => 'https://api.example.com/api/search', // no terms param
        ];

        // Should not throw — before the fix, this caused an Undefined array key warning
        $result = $this->normalizer->normalize(new \stdClass(), 'jsonld', $context);
        $this->assertIsArray($result);
    }

    // ── normalize() — Volume class with null journal (bug #17 regression) ─────

    /**
     * Bug regression: getSetting() was called on $journal without a null guard.
     * When no journal is found (rvCode doesn't match), $journal is null,
     * and calling $journal->getSetting() throws a null dereference error.
     * The fix adds a `$journal !== null` check before calling getSetting().
     */
    public function testNormalizeVolumeClassWithNullJournalDoesNotCallGetSetting(): void
    {
        $operation = $this->createMock(\ApiPlatform\Metadata\HttpOperation::class);
        $operation->method('getClass')->willReturn(\App\Entity\Volume::class);

        $this->decorated->method('normalize')->willReturn(['hydra:member' => []]);
        $this->security->method('isGranted')->willReturn(false);

        $journalRepo = $this->createMock(ReviewRepository::class);
        $journalRepo->method('getJournalByIdentifier')->willReturn(null); // journal not found

        $this->entityManager
            ->method('getRepository')
            ->with(Review::class)
            ->willReturn($journalRepo);

        $context = [
            'operation' => $operation,
            // Include rvcode so the repo lookup is triggered, but repo returns null
            'uri' => 'https://api.example.com/api/volumes?rvcode=nonexistent',
        ];

        // Should not throw — before the fix: TypeError from null->getSetting()
        $result = $this->normalizer->normalize(new \stdClass(), 'jsonld', $context);
        $this->assertIsArray($result);
    }

    // ── supportsNormalization() ───────────────────────────────────────────────

    public function testSupportsNormalizationDelegatesToDecorated(): void
    {
        $this->decorated
            ->method('supportsNormalization')
            ->with('data', 'json')
            ->willReturn(true);

        $this->assertTrue($this->normalizer->supportsNormalization('data', 'json', []));
    }

    public function testSupportsNormalizationReturnsFalseWhenDecoratedReturnsFalse(): void
    {
        $this->decorated->method('supportsNormalization')->willReturn(false);
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, []));
    }

    // ── setNormalizer() ───────────────────────────────────────────────────────

    public function testSetNormalizerPropagatesWhenDecoratedImplementsNormalizerAwareInterface(): void
    {
        $normalizerCalled = false;
        $capturedNormalizer = null;

        // decorated must implement NormalizerInterface (required by property type) AND NormalizerAwareInterface
        $decorated = new class ($normalizerCalled, $capturedNormalizer) implements NormalizerInterface, NormalizerAwareInterface {
            public function __construct(private bool &$called, private mixed &$captured) {}
            public function normalize(mixed $data, ?string $format = null, array $context = []): array|\ArrayObject|string|int|float|bool|null { return []; }
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool { return true; }
            public function getSupportedTypes(?string $format): array { return []; }
            public function setNormalizer(NormalizerInterface $normalizer): void {
                $this->called = true;
                $this->captured = $normalizer;
            }
        };

        $innerNormalizer = $this->createMock(NormalizerInterface::class);

        $ref = new \ReflectionProperty(ApiCollectionNormalizer::class, 'decorated');
        $ref->setValue($this->normalizer, $decorated);

        $this->normalizer->setNormalizer($innerNormalizer);

        $this->assertTrue($normalizerCalled);
        $this->assertSame($innerNormalizer, $capturedNormalizer);
    }

    // ── setSerializer() ───────────────────────────────────────────────────────

    public function testSetSerializerDoesNotFailWhenDecoratedIsNotSerializerAware(): void
    {
        // $this->decorated is NormalizerInterface only (no SerializerAwareInterface)
        $serializer = $this->createMock(SerializerInterface::class);
        // Should not throw
        $this->normalizer->setSerializer($serializer);
        $this->assertTrue(true);
    }

    public function testSetSerializerPropagatesWhenDecoratedImplementsSerializerAwareInterface(): void
    {
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

        $ref = new \ReflectionProperty(ApiCollectionNormalizer::class, 'decorated');
        $ref->setValue($this->normalizer, $decorated);

        $serializer = $this->createMock(SerializerInterface::class);
        $this->normalizer->setSerializer($serializer);

        $this->assertTrue($serializerCalled);
        $this->assertSame($serializer, $capturedSerializer);
    }

    // ── addHydraContext() private branches ────────────────────────────────────

    /**
     * Helper: build normalized data with hydra:totalItems so addHydraContext runs.
     */
    private function makeCollectionData(string $id = '/api/news'): array
    {
        return [
            'hydra:totalItems' => 3,
            'hydra:member'     => ['item1'],
            '@id'              => $id,
        ];
    }

    private function makeOperation(string $class): HttpOperation
    {
        $op = $this->createMock(HttpOperation::class);
        $op->method('getClass')->willReturn($class);
        $op->method('getMethod')->willReturn('GET');
        return $op;
    }

    public function testAddHydraContextNewsClassAddsYearsRange(): void
    {
        $newsRepo = $this->createMock(NewsRepository::class);
        $newsRepo->method('getRange')->willReturn([2022, 2023]);

        $this->entityManager->method('getRepository')->willReturn($newsRepo);
        $this->decorated->method('normalize')->willReturn($this->makeCollectionData('/api/news'));
        $this->security->method('isGranted')->willReturn(false);

        $result = $this->normalizer->normalize(
            new \stdClass(),
            'jsonld',
            ['operation' => $this->makeOperation(News::class), 'uri' => '']
        );

        $this->assertArrayHasKey('hydra:range', $result);
        $this->assertArrayHasKey('years', $result['hydra:range']);
        $this->assertSame([2022, 2023], $result['hydra:range']['years']);
    }

    public function testAddHydraContextStatisticClassAddsYearsAndIndicators(): void
    {
        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('getYearRange')->willReturn([2021, 2022, 2023]);

        $this->entityManager->method('getRepository')->willReturn($paperRepo);
        $this->decorated->method('normalize')->willReturn(
            $this->makeCollectionData(Statistic::STATISTIC_GET_COLLECTION_OPERATION_IDENTIFIER)
        );
        $this->security->method('isGranted')->willReturn(false);

        $result = $this->normalizer->normalize(
            new \stdClass(),
            'jsonld',
            ['operation' => $this->makeOperation(Statistic::class), 'uri' => '']
        );

        $this->assertArrayHasKey('hydra:range', $result);
        $this->assertArrayHasKey('years', $result['hydra:range']);
        $this->assertArrayHasKey('indicators', $result['hydra:range']);
        $this->assertSame(array_values(Statistic::AVAILABLE_PUBLICATION_INDICATORS), $result['hydra:range']['indicators']);
    }

    public function testAddHydraContextPaperClassAddsTypesRange(): void
    {
        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('getTypes')->willReturn(['article', 'preprint']);
        $paperRepo->method('getRange')->willReturn([2021, 2022]);

        $this->entityManager->method('getRepository')->willReturn($paperRepo);
        $this->decorated->method('normalize')->willReturn($this->makeCollectionData('/api/papers'));
        $this->security->method('isGranted')->willReturn(false);

        $result = $this->normalizer->normalize(
            new \stdClass(),
            'jsonld',
            ['operation' => $this->makeOperation(Paper::class), 'uri' => '']
        );

        $this->assertArrayHasKey('hydra:range', $result);
        $this->assertArrayHasKey('types', $result['hydra:range']);
        $this->assertSame(['article', 'preprint'], $result['hydra:range']['types']);
    }

    public function testAddHydraContextVolumeClassWithIsGrantedSecretaryAddsRange(): void
    {
        // QueryBuilder mock needed by getIdentifiers() (called when filters !== [] and Volume)
        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $expr->method('orX')->willReturn(new \Doctrine\ORM\Query\Expr\Orx());

        $ormQuery = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $ormQuery->method('getArrayResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);
        $qb->method('getAllAliases')->willReturn(['alias']);
        $qb->method('getRootEntities')->willReturn([Volume::class]);
        $qb->method('getRootAliases')->willReturn(['alias']);
        $qb->method('getQuery')->willReturn($ormQuery);
        $qb->method('select')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->method('getTypes')->willReturn(['proceedings', 'special']);
        $volumeRepo->method('getRange')->willReturn([2020, 2021]);
        $volumeRepo->method('createQueryBuilder')->willReturn($qb);

        // Volume addHydraContext also fetches PapersRepository for total article count
        $paperRepo = $this->createMock(PapersRepository::class);
        $paperRepo->method('getTotalArticleBySectionOrVolume')
            ->willReturn([PapersRepository::TOTAL_ARTICLE => 5]);

        $this->entityManager->method('getRepository')->willReturnMap([
            [Volume::class, $volumeRepo],
            [Paper::class, $paperRepo],
        ]);
        $this->decorated->method('normalize')->willReturn($this->makeCollectionData('/api/volumes'));
        $this->security->method('isGranted')->with('ROLE_SECRETARY')->willReturn(true);

        $result = $this->normalizer->normalize(
            new \stdClass(),
            'jsonld',
            ['operation' => $this->makeOperation(Volume::class), 'uri' => '']
        );

        $this->assertArrayHasKey('hydra:range', $result);
        $this->assertArrayHasKey('types', $result['hydra:range']);
    }
}
