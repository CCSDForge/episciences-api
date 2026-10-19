<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\AppConstants;
use App\Doctrine\PaperCollectionPartialExtension;
use App\Entity\Paper;
use App\Entity\Section;
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
 * Uses the real Doctrine and serializer mappings, so a change in the normalization
 * groups of Paper is reflected here.
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
        $queryBuilder = $this->createQueryBuilder(Paper::class);

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Paper::class,
            new GetCollection(normalizationContext: ['groups' => ['read:Papers']])
        );

        $this->assertMatchesRegularExpression('/^SELECT partial o\.\{([a-z,]+)\} FROM App\\\\Entity\\\\Paper o$/i', $queryBuilder->getDQL());
        $fields = $this->getPartialFields($queryBuilder, 'o');

        $this->assertEqualsCanonicalizing(['docid', 'rvid', 'paperid', 'doi', 'status'], $fields);
        $this->assertNotContains('record', $fields);
        $this->assertNotContains('document', $fields);
    }

    public function testPapersFetchJoinedInAnotherCollectionLoadOnlySerializedFields(): void
    {
        $queryBuilder = $this->createQueryBuilder(Section::class)
            ->addSelect('papers_a1')
            ->leftJoin('o.papers', 'papers_a1');

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Section::class,
            new GetCollection(normalizationContext: ['groups' => [AppConstants::APP_CONST['normalizationContext']['groups']['section']['collection']['read'][0]]])
        );

        $this->assertStringStartsWith('SELECT o, partial papers_a1.{', $queryBuilder->getDQL());

        // status is read to count the published papers (AbstractVolumeSection::setTotalPublishedArticles())
        $this->assertEqualsCanonicalizing(['docid', 'rvid', 'status', 'paperid'], $this->getPartialFields($queryBuilder, 'papers_a1'));
    }

    public function testCollectionWithoutGroupsIsUnchanged(): void
    {
        $queryBuilder = $this->createQueryBuilder(Paper::class);

        $this->extension->applyToCollection($queryBuilder, $this->queryNameGenerator, Paper::class, new GetCollection());

        $this->assertSame(self::FULL_SELECT, $queryBuilder->getDQL());
    }

    public function testItemOperationIsUnchanged(): void
    {
        $queryBuilder = $this->createQueryBuilder(Paper::class);

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Paper::class,
            new Get(normalizationContext: ['groups' => ['read:Paper']])
        );

        $this->assertSame(self::FULL_SELECT, $queryBuilder->getDQL());
    }

    public function testCollectionWithoutPapersIsUnchanged(): void
    {
        $queryBuilder = $this->createQueryBuilder(Volume::class);

        $this->extension->applyToCollection(
            $queryBuilder,
            $this->queryNameGenerator,
            Volume::class,
            new GetCollection(normalizationContext: ['groups' => ['read:Volumes']])
        );

        $this->assertSame('SELECT o FROM App\Entity\Volume o', $queryBuilder->getDQL());
    }

    /**
     * @return string[]
     */
    private function getPartialFields(QueryBuilder $queryBuilder, string $alias): array
    {
        preg_match(sprintf('/partial %s\.\{([^}]+)\}/', preg_quote($alias, '/')), $queryBuilder->getDQL(), $matches);
        $this->assertNotEmpty($matches, sprintf('No partial select of "%s"', $alias));

        return explode(',', $matches[1]);
    }

    /**
     * @param class-string $rootEntity
     */
    private function createQueryBuilder(string $rootEntity): QueryBuilder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnCallback(static function (string $class): ClassMetadata {
            $metadata = new ClassMetadata($class);
            $metadata->initializeReflection(new RuntimeReflectionService());
            new AttributeDriver([])->loadMetadataForClass($class, $metadata);

            return $metadata;
        });

        return new QueryBuilder($entityManager)->select('o')->from($rootEntity, 'o');
    }
}
