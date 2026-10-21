<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Paper;
use App\Serializer\Normalizer\ApiCollectionNormalizer;
use App\Service\Solarium\Client;
use App\Service\Solr\SolrAuthorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiCollectionNormalizerTest extends TestCase
{
    private const array NESTED_DATA = [['name' => 'a'], ['name' => 'b']];

    public function testSupportsTheSameTypesAsTheHydraCollectionNormalizer(): void
    {
        $supportedTypes = ['native-array' => true, '\Traversable' => true];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('getSupportedTypes')->willReturn($supportedTypes);

        $this->assertSame($supportedTypes, $this->createNormalizer($decorated)->getSupportedTypes(ApiCollectionNormalizer::FORMAT));
    }

    public function testSupportsNormalizationPassesTheContext(): void
    {
        $context = ['resource_class' => Paper::class];

        $decorated = $this->createMock(NormalizerInterface::class);
        $decorated->expects($this->once())->method('supportsNormalization')->with([], ApiCollectionNormalizer::FORMAT, $context)->willReturn(true);

        $this->assertTrue($this->createNormalizer($decorated)->supportsNormalization([], ApiCollectionNormalizer::FORMAT, $context));
    }

    public function testArrayNestedInAResourceIsReturnedAsIs(): void
    {
        $context = [
            'resource_class' => Paper::class,
            'api_sub_level' => true,
            'operation' => new GetCollection(class: Paper::class),
            'uri' => '/api/papers/?pagination=false&rvcode=epijinfo',
        ];

        $this->assertSame(self::NESTED_DATA, $this->normalizeNested($context));
    }

    public function testArrayWithoutResourceClassIsReturnedAsIs(): void
    {
        $this->assertSame(self::NESTED_DATA, $this->normalizeNested(['operation' => new GetCollection(class: Paper::class)]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function normalizeNested(array $context): mixed
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn(self::NESTED_DATA);

        // A nested array is not a collection: no journal lookup nor hydra context
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');

        return $this->createNormalizer($decorated, $entityManager)->normalize(self::NESTED_DATA, ApiCollectionNormalizer::FORMAT, $context);
    }

    private function createNormalizer(NormalizerInterface $decorated, ?EntityManagerInterface $entityManager = null): ApiCollectionNormalizer
    {
        return new ApiCollectionNormalizer(
            $decorated,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(SolrAuthorService::class),
            $this->createStub(Client::class),
            null,
            $this->createStub(Security::class)
        );
    }
}
