<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Paper;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Hydrates the Paper collection with partial objects so that only the fields
 * actually serialized in the collection (normalization groups of the operation)
 * are loaded.
 *
 * Without this, every paper row is fully hydrated including the heavy scalar
 * columns RECORD (TEXT) and DOCUMENT (JSON, decoded to PHP arrays), which made
 * /api/papers/?pagination=false exhaust the PHP memory limit (500).
 *
 * The field list is derived from the serializer metadata, so a field added to
 * the collection groups is loaded without touching this class. Fields that are
 * not loaded stay uninitialized on the hydrated objects: do not read them while
 * handling the collection.
 */
class PaperCollectionPartialExtension implements QueryCollectionExtensionInterface
{
    /**
     * Loaded even if not serialized: PapersVoter reads rvid.
     */
    private const array ALWAYS_LOADED_FIELDS = ['rvid'];

    public function __construct(private readonly ClassMetadataFactoryInterface $serializerMetadataFactory)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        if ($resourceClass !== Paper::class || !$operation instanceof GetCollection) {
            return;
        }

        $groups = (array)($operation->getNormalizationContext()[AbstractNormalizer::GROUPS] ?? []);

        if ($groups === []) { // every property is serialized: nothing to skip
            return;
        }

        $fields = $this->getSerializedFields($queryBuilder->getEntityManager()->getClassMetadata(Paper::class), $groups);

        // review (rvid, code) is joined and selected by API Platform's EagerLoadingExtension.
        $queryBuilder->select(sprintf('partial %s.{%s}', $queryBuilder->getRootAliases()[0], implode(',', $fields)));
    }

    /**
     * @param ClassMetadata<Paper> $doctrineMetadata
     * @param string[] $groups
     * @return string[] identifier, always loaded fields, then the mapped fields serialized in $groups
     */
    private function getSerializedFields(ClassMetadata $doctrineMetadata, array $groups): array
    {
        $fields = [...$doctrineMetadata->getIdentifierFieldNames(), ...self::ALWAYS_LOADED_FIELDS];

        foreach ($this->serializerMetadataFactory->getMetadataFor($doctrineMetadata->getName())->getAttributesMetadata() as $name => $attributeMetadata) {
            if ($doctrineMetadata->hasField($name) && array_intersect($attributeMetadata->getGroups(), $groups) !== []) {
                $fields[] = $name;
            }
        }

        return array_values(array_unique($fields));
    }
}
