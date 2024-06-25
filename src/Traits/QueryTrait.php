<?php

namespace App\Traits;

use App\Entity\News;
use App\Entity\Section;
use App\Entity\Volume;
use Doctrine\ORM\QueryBuilder;

trait QueryTrait
{

    final public function processOrExpression(QueryBuilder $qb, string $alias, array $values, string $resourceClass): QueryBuilder
    {

        if (count($values) === 0) {
            return $qb;
        }

        if ($resourceClass === Volume::class) {
            $yearExp = "$alias.vol_year";
        } elseif ($resourceClass === News::class) {
            $yearExp = "YEAR($alias.date_creation)";

        } else {
            return $qb;
        }

        $orExp = $qb->expr()->orX();

        foreach ($values as $val) {
            $orExp->add($qb->expr()->eq(sprintf("%s", $yearExp), $val));
        }

        return $qb->andWhere($orExp);

    }

    final public function processYears(string|array $yFilters = []): array
    {
        $yFilters = (array)$yFilters;

        $processedYears = [];

        foreach ($yFilters as $yVal) {

            $yVal = (int)$yVal;

            if (!in_array($yVal, $processedYears, true)) {
                $processedYears[] = $yVal;
            }
        }

        return $processedYears;
    }


    final public function processTypes(QueryBuilder $qb, array $filters): string
    {

        $currentTypes = [];
        $availableTypes = $qb->getEntityManager()->getRepository(Volume::class)->getTypes();

        foreach ($filters as $value) {
            $value = trim($value);
            if (in_array($value, $availableTypes, true) && !in_array($value, $currentTypes, true)) {
                $currentTypes[] = $value;
            }
        }

        return !empty($currentTypes) ? implode(',', $currentTypes) : implode(',', $filters);

    }

    final public function getIdentifiers(QueryBuilder $qb,array $filters = []): array
    {
        $resourceClass = $qb->getRootEntities()[0];

        $rIdentifier = null;
        $alias = $qb->getRootAliases()[0];
        $identifiers = [];

        if ($resourceClass === Volume::class) {
            $rIdentifier = 'vid';
        } elseif ($resourceClass === Section::class) {
            $rIdentifier = 'sid';
        }

        if (!$rIdentifier) {
            return [];
        }

        $qb->select(sprintf('%s.%s', $alias, $rIdentifier));

        if (isset($filters['rvid'])) {
            $qb->andWhere(sprintf('%s.rvid =:rvId', $alias));
            $qb->setParameter('rvId', $filters['rvid']);
        }


        if (isset($filters['year'])) {
            $years = $this->processYears($filters['year']);
            $this->processOrExpression($qb, $alias, $years, $resourceClass);

        }

        if (isset($filters['type'])) {
            $volType = $this->processTypes($qb, $filters['type']);

            if ('' !== $volType) {
                $qb->andWhere(sprintf('%s.vol_type like :volType', $alias));
                $qb->setParameter('volType', '%' . $volType . '%');
            }
        }

        $result = $qb->getQuery()->getArrayResult();

        foreach ($result as $value) {
            $identifiers[] = $value[$rIdentifier];
        }

        return $identifiers;

    }
}