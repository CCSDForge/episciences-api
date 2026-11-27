<?php

namespace App\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * Counts the root entities matching a collection query, whatever its first result,
 * its maximum number of results and its to-many joins (COUNT DISTINCT of Doctrine's Paginator).
 */
class CollectionItemCounter
{
    public function count(QueryBuilder $queryBuilder): int
    {
        return count(new DoctrinePaginator($queryBuilder->getQuery()));
    }
}
