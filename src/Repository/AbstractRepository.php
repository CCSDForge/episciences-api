<?php

namespace App\Repository;

use App\AppConstants;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;

abstract class AbstractRepository extends ServiceEntityRepository
{
    public const DEFAULT_MAX_RESULT = 100; // used without pagination
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }


    /**
     * Generic paginated query for any entity extending this repository.
     *
     * @param int $page Requested page (1-based)
     * @param int $itemsPerPage Number of items per page
     * @return DoctrinePaginator Paginated results
     */
    public function getPaginatedItems(QueryBuilder $qb, int $page = 1, int $itemsPerPage = AppConstants::DEFAULT_ITEM_PER_PAGE): DoctrinePaginator
    {

        $qb->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        return new DoctrinePaginator($qb->getQuery(), false);
    }
}
