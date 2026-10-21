<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Paper;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Hydrates the papers of a collection with partial objects so that only the fields
 * actually serialized (normalization groups of the operation) are loaded: the Paper
 * collection itself, and the papers fetch-joined in another collection (e.g. sections).
 *
 * Without this, every paper row is fully hydrated including the heavy scalar
 * columns RECORD (TEXT) and DOCUMENT (JSON, decoded to PHP arrays), which made
 * /api/papers/?pagination=false exhaust the PHP memory limit (500).
 *
 * The field list is derived from the serializer metadata, so a field added to
 * the collection groups is loaded without touching this class. Fields that are
 * not loaded stay uninitialized on the hydrated objects: do not read them while
 * handling the collection.
 *
 * Applied after API Platform's EagerLoadingExtension (priority -18), which adds the fetch joins.
 */
#[AsTaggedItem(priority: -20)]
class PaperCollectionPartialExtension implements QueryCollectionExtensionInterface
{
    /**
     * Loaded even if not serialized: PapersVoter reads rvid,
     * AbstractVolumeSection::setTotalPublishedArticles() reads status.
     */
    private const array ALWAYS_LOADED_FIELDS = ['rvid', 'status'];

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
        if (!$operation instanceof GetCollection) {
            return;
        }

        $groups = (array)($operation->getNormalizationContext()[AbstractNormalizer::GROUPS] ?? []);

        if ($groups === []) { // every property is serialized: nothing to skip
            return;
        }

        $paperAliases = $this->getPaperAliases($queryBuilder);

        if ($paperAliases === []) {
            return;
        }

        $partial = sprintf(
            'partial %%s.{%s}',
            implode(',', $this->getSerializedFields($queryBuilder->getEntityManager()->getClassMetadata(Paper::class), $groups))
        );

        $selectParts = [];

        foreach ($queryBuilder->getDQLPart('select') as $select) {
            foreach ($select instanceof Select ? $select->getParts() : [(string)$select] as $part) {
                $selectParts[] = in_array(trim((string)$part), $paperAliases, true) ? sprintf($partial, trim((string)$part)) : $part;
            }
        }

        $queryBuilder->select($selectParts);
    }

    /**
     * @return string[] aliases of the Paper entities in the query: root entity or joined association
     */
    private function getPaperAliases(QueryBuilder $queryBuilder): array
    {
        $entityManager = $queryBuilder->getEntityManager();
        $classByAlias = array_combine($queryBuilder->getRootAliases(), $queryBuilder->getRootEntities());

        foreach ($queryBuilder->getDQLPart('join') as $joins) {
            /** @var Join $join */
            foreach ($joins as $join) {
                [$parentAlias, $association] = array_pad(explode('.', $join->getJoin(), 2), 2, null);

                if ($association !== null && isset($classByAlias[$parentAlias])) {
                    $classByAlias[$join->getAlias()] = $entityManager->getClassMetadata($classByAlias[$parentAlias])->getAssociationTargetClass($association);
                }
            }
        }

        return array_keys(array_filter($classByAlias, static fn(string $class): bool => $class === Paper::class));
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
