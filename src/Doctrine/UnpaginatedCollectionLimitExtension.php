<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryChecker;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\AppConstants;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Caps the Doctrine collections returned without pagination (?pagination=false)
 * to the global maximum number of items per page, to avoid possible OUT OF MEMORY
 * errors. The maximum of the operation is not used: it is meant for a page
 * (e.g. 30 for the journals), not for the whole collection.
 *
 * One item more than the maximum is fetched to tell a truncated result: it is
 * removed, and the maximum is stored in the request (TRUNCATED_ATTRIBUTE) so that
 * TruncatedCollectionSubscriber can flag the response with a header.
 *
 * Applied last (lowest priority): returning the result stops the other extensions.
 *
 * @implements QueryResultCollectionExtensionInterface<object>
 */
#[AsTaggedItem(priority: -100)]
final class UnpaginatedCollectionLimitExtension implements QueryResultCollectionExtensionInterface
{
    public const string TRUNCATED_ATTRIBUTE = '_app_collection_truncated_max_items';

    public function __construct(
        private readonly Pagination $pagination,
        private readonly ManagerRegistry $managerRegistry,
        private readonly RequestStack $requestStack
    )
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
    }

    public function supportsResult(string $resourceClass, ?Operation $operation = null, array $context = []): bool
    {
        return
            $operation instanceof CollectionOperationInterface &&
            !isset($context['graphql_operation_name']) &&
            !$this->pagination->isEnabled($operation, $context);
    }

    /**
     * @return object[]
     */
    public function getResult(QueryBuilder $queryBuilder, ?string $resourceClass = null, ?Operation $operation = null, array $context = []): iterable
    {
        $maxItems = $this->getMaxItems();

        $queryBuilder->setFirstResult(0)->setMaxResults($maxItems + 1);

        $items = $this->fetch($queryBuilder);

        if (count($items) <= $maxItems) {
            return $items;
        }

        $this->requestStack->getCurrentRequest()?->attributes->set(self::TRUNCATED_ATTRIBUTE, $maxItems);

        return array_slice($items, 0, $maxItems);
    }

    private function getMaxItems(): int
    {
        return $this->pagination->getOptions()['maximum_items_per_page'] ?? AppConstants::MAXIMUM_ITEMS_PER_PAGE;
    }

    /**
     * @return object[]
     */
    private function fetch(QueryBuilder $queryBuilder): array
    {
        if (!QueryChecker::hasJoinedToManyAssociation($queryBuilder, $this->managerRegistry)) {
            return $queryBuilder->getQuery()->getResult();
        }

        // With a to-many join, a SQL LIMIT counts the joined rows, not the root entities
        // (truncated fetch-joined collections): limit the root entities instead.
        return iterator_to_array(new DoctrinePaginator($queryBuilder->getQuery(), true), false);
    }
}
