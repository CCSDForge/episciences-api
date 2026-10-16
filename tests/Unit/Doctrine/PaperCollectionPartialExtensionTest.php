<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\AppConstants;
use App\Doctrine\PaperCollectionPartialExtension;
use App\Entity\Paper;
use App\Entity\Volume;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

/**
 * Uses the real Doctrine and serializer mappings of Paper, so a change in the
 * "read:Papers" group is reflected here.
 */
class PaperCollectionPartialExtensionTest extends TestCase
{
    private const string FULL_SELECT = 'SELECT o FROM App\Entity\Paper o';

    private PaperCollectionPartialExtension $extension;
    private QueryNameGeneratorInterface $queryNameGenerator;

    protected function setUp(): void
    {
        $this->extension = new PaperCollectionPartialExtension(new ClassMetadataFactory(new AttributeLoader()));
        $this->queryNameGenerator = $this->createStub(QueryNameGeneratorInterface::class);
    }

    public function testCollectionLoadsOnlySerializedFields(): void
    {
        $queryBuilder = $this->createPaperQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Paper::class,
            new GetCollection(normalizationContext: ['groups' => ['read:Papers']])
        );

        $this->assertMatchesRegularExpression('/^SELECT partial o\.\{([a-z,]+)\} FROM App\\\\Entity\\\\Paper o$/i', $queryBuilder->getDQL());
        preg_match('/\{(.+)\}/', $queryBuilder->getDQL(), $matches);
        $fields = explode(',', $matches[1]);

        $this->assertEqualsCanonicalizing(['docid', 'rvid', 'paperid', 'doi', 'status'], $fields);
        $this->assertNotContains('record', $fields);
        $this->assertNotContains('document', $fields);
        $this->assertSame(AppConstants::MAXIMUM_ITEMS_PER_PAGE, $queryBuilder->getMaxResults());
    }

    public function testCollectionIsCappedToTheOperationMaximumItemsPerPage(): void
    {
        $queryBuilder = $this->createPaperQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Paper::class,
            new GetCollection(paginationMaximumItemsPerPage: 50, normalizationContext: ['groups' => ['read:Papers']])
        );

        $this->assertSame(50, $queryBuilder->getMaxResults());
    }

    public function testCollectionWithoutGroupsKeepsTheFullSelect(): void
    {
        $queryBuilder = $this->createPaperQueryBuilder();

        $this->extension->applyToCollection($queryBuilder, $this->queryNameGenerator, Paper::class, new GetCollection());

        $this->assertSame(self::FULL_SELECT, $queryBuilder->getDQL());
        $this->assertSame(AppConstants::MAXIMUM_ITEMS_PER_PAGE, $queryBuilder->getMaxResults());
    }

    public function testItemOperationIsUnchanged(): void
    {
        $queryBuilder = $this->createPaperQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Paper::class,
            new Get(normalizationContext: ['groups' => ['read:Paper']])
        );

        $this->assertSame(self::FULL_SELECT, $queryBuilder->getDQL());
        $this->assertNull($queryBuilder->getMaxResults());
    }

    public function testOtherResourceIsUnchanged(): void
    {
        $queryBuilder = $this->createPaperQueryBuilder();

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Volume::class,
            new GetCollection(normalizationContext: ['groups' => ['read:Papers']])
        );

        $this->assertSame(self::FULL_SELECT, $queryBuilder->getDQL());
        $this->assertNull($queryBuilder->getMaxResults());
    }

    private function createPaperQueryBuilder(): QueryBuilder
    {
        $metadata = new ClassMetadata(Paper::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([])->loadMetadataForClass(Paper::class, $metadata);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return new QueryBuilder($entityManager)->select('o')->from(Paper::class, 'o');
    }
}
