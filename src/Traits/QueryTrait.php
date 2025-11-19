<?php

namespace App\Traits;

use App\Entity\News;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
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

        return $this->andOrExp($qb, sprintf("%s", $yearExp), $values);

    }

    final public function processYears(string|array $yFilters = []): array
    {
        if (is_string($yFilters)) {
            $yFilters = (array($yFilters));
        }

        $yFilters = array_unique($yFilters);

        return array_filter($yFilters, static function ($val) { // Supprime à la fois les valeurs nulles et les valeurs vides
            return !empty($val);
        });

    }

    /**
     * Cleans and removes duplicates, and if valid filters are present, returns them.
     * @param QueryBuilder $qb
     * @param array|string $filters
     * @return array
     */

    final public function processTypes(QueryBuilder $qb, array|string $filters): array
    {
        if (is_string($filters)) {
            $filters = (array)$filters;
        }

        $availableCurrentTypes = [];
        $unavailableCurrentTypes = [];
        /** @var VolumeRepository $volRepo */
        $volRepo = $qb->getEntityManager()->getRepository(Volume::class);
        $availableTypes = $volRepo->getTypes();
        $arrayUnique = array_unique($filters);

        foreach ($arrayUnique as $value) {
            $value = trim($value);
            if (in_array($value, $availableTypes, true) && !in_array($value, $availableCurrentTypes, true)) {
                $availableCurrentTypes[] = $value;
            } elseif (!in_array($value, $unavailableCurrentTypes, true)) {
                $unavailableCurrentTypes[] = $value;
            }
        }

        // Type SET en MySQL n’est pas nativement supporté par Doctrine ORM
        return !empty($availableCurrentTypes) ? $availableCurrentTypes : $unavailableCurrentTypes;

    }

    final public function getIdentifiers(QueryBuilder $qb, array $filters = []): array
    {
        $resourceClass = $qb->getRootEntities()[0];

        $rIdentifier = null;

        $alias = $qb->getRootAliases()[0];

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
            $volTypes = $this->processTypes($qb, $filters['type']);
            $this->andOrLikeExp($qb, sprintf('%s.vol_type', $alias), $volTypes);
        }

        return array_column(array_values($qb->getQuery()->getArrayResult()), $rIdentifier);

    }

    final public function andOrExp(QueryBuilder $qb, string $expression, array $values = []): QueryBuilder
    {

        if (empty($values)) {
            return $qb;
        }

        $orExp = $qb->expr()->orX();

        foreach ($values as $val) {
            $orExp->add($qb->expr()->eq($expression, $qb->expr()->literal($val)));
        }

        return $qb->andWhere($orExp);
    }

    final public function andOrLikeExp(QueryBuilder $qb, string $expression, array $values = [], bool $isCaseInsensitive = true): QueryBuilder
    {

        if (empty($values)) {
            return $qb;
        }
        $orExp = $qb->expr()->orX();

        foreach ($values as $val) {

            if ($isCaseInsensitive) {
                $val = strtolower($val);
            }

            $orExp->add($qb->expr()->like($expression, $qb->expr()->literal('%' . $val . '%')));
        }

        return $qb->andWhere($orExp);
    }
}
