<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Makes the order of the Doctrine collections deterministic: the identifier of the root
 * entity is added as the last sort criterion, unless it is already sorted on.
 *
 * Without a unique last criterion, MySQL does not guarantee the order of the rows that
 * share the same sort values (e.g. papers with the same publication date) from one
 * LIMIT/OFFSET query to the next: walking the pages returned some items twice and
 * never returned others.
 *
 * The identifier follows the direction of the last existing criterion (the most recent
 * paper first when sorted by date DESC); with no sort at all, OrderExtension has already
 * sorted on the identifier.
 *
 * Applied after the filters (OrderFilter, -16) and API Platform's OrderExtension (-32), which
 * add the requested and default sorts, and before the pagination (-64) and the cap of the
 * unpaginated collections (-100), which run the query.
 */
#[AsTaggedItem(priority: -40)]
final class StableCollectionOrderExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        $orderBy = $this->getOrderByParts($queryBuilder);

        if ($orderBy === []) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;
        $rootEntity = $queryBuilder->getRootEntities()[0] ?? null;

        if ($rootAlias === null || $rootEntity === null) {
            return;
        }

        $metadata = $queryBuilder->getEntityManager()->getClassMetadata($rootEntity);

        // A foreign identifier cannot be used for ordering (as in OrderExtension).
        if ($metadata->containsForeignIdentifier) {
            return;
        }

        $direction = $this->getLastDirection($orderBy);

        foreach ($metadata->getIdentifier() as $identifier) {
            $field = "$rootAlias.$identifier";

            if (!$this->isSortedOn($orderBy, $field)) {
                $queryBuilder->addOrderBy($field, $direction);
            }
        }
    }

    /**
     * @return list<string> e.g. ['o.publicationDate DESC']
     */
    private function getOrderByParts(QueryBuilder $queryBuilder): array
    {
        $parts = [];

        $orderByPart = $queryBuilder->getDQLPart('orderBy');

        foreach (is_array($orderByPart) ? $orderByPart : [] as $orderBy) {
            if (!$orderBy instanceof OrderBy) {
                continue;
            }

            foreach ($orderBy->getParts() as $part) {
                $parts[] = trim($part);
            }
        }

        return $parts;
    }

    /**
     * @param list<string> $orderBy
     */
    private function getLastDirection(array $orderBy): string
    {
        return preg_match('/\sDESC$/i', (string)end($orderBy)) === 1 ? AppConstants::ORDER_DESC : AppConstants::ORDER_ASC;
    }

    /**
     * @param list<string> $orderBy
     */
    private function isSortedOn(array $orderBy, string $field): bool
    {
        foreach ($orderBy as $part) {
            if (explode(' ', $part, 2)[0] === $field) {
                return true;
            }
        }

        return false;
    }
}
