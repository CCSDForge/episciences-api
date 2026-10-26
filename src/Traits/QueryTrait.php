<?php

namespace App\Traits;

use App\Entity\News;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use Doctrine\ORM\QueryBuilder;

trait QueryTrait
{
    /** SQL condition: the statistics without journal only take into account the active journals */
    public const string ACTIVE_JOURNALS_SQL = 'SELECT RVID FROM REVIEW WHERE STATUS = ' . Review::STATUS_ENABLED;

    /**
     * The statistics without journal only take into account the active journals,
     * as the statistics of a journal (a disabled journal cannot be requested).
     */
    final public function andActiveJournals(QueryBuilder $qb, string $rvIdField): QueryBuilder
    {
        return $qb
            ->andWhere(sprintf('%s IN (SELECT activeJournal.rvid FROM %s activeJournal WHERE activeJournal.status = :activeJournalStatus)', $rvIdField, Review::class))
            ->setParameter('activeJournalStatus', Review::STATUS_ENABLED);
    }

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

        return $this->andOrExp($qb, $yearExp, $values);

    }

    final public function processYears(string|array $yFilters = []): array
    {
        if (is_string($yFilters)) {
            $yFilters = ([$yFilters]);
        }

        $yFilters = array_unique($yFilters);

        return array_filter($yFilters,
            // Supprime à la fois les valeurs nulles et les valeurs vides
            static fn($val): bool => !empty($val));

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
            $value = trim((string) $value);
            if (in_array($value, $availableTypes, true) && !in_array($value, $availableCurrentTypes, true)) {
                $availableCurrentTypes[] = $value;
            } elseif (!in_array($value, $unavailableCurrentTypes, true)) {
                $unavailableCurrentTypes[] = $value;
            }
        }

        // Type SET en MySQL n’est pas nativement supporté par Doctrine ORM
        return $availableCurrentTypes === [] ? $unavailableCurrentTypes : $availableCurrentTypes;

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

            if ($resourceClass === Volume::class) {
                // same filter as VolumeRepository::listQuery(): a volume year can be a range (e.g. "2024-2025")
                $this->andOrLikeExp($qb, sprintf('%s.vol_year', $alias), $years);
            } else {
                $this->processOrExpression($qb, $alias, $years, $resourceClass);
            }
        }

        if (isset($filters['type'])) {
            $volTypes = $this->processTypes($qb, $filters['type']);
            $this->andOrLikeExp($qb, sprintf('%s.vol_type', $alias), $volTypes);
        }

        return array_column(array_values($qb->getQuery()->getArrayResult()), $rIdentifier);

    }

    final public function andOrExp(QueryBuilder $qb, string $expression, array $values = [], $isAllowedToReceiveEmptyValues = true): QueryBuilder
    {

        if ($values === []) {

            if ($isAllowedToReceiveEmptyValues) {
                return $qb;
            }

            return $qb->andWhere('1 = 0');
        }

        $orExp = $qb->expr()->orX();

        foreach ($values as $val) {
            $orExp->add($qb->expr()->eq($expression, $qb->expr()->literal($val)));
        }

        return $qb->andWhere($orExp);
    }

    final public function andOrLikeExp(QueryBuilder $qb, string $expression, array $values = [], bool $isCaseInsensitive = true, bool $isAllowedToReceiveEmptyValues = true): QueryBuilder
    {

        if ($values === []) {

            if ($isAllowedToReceiveEmptyValues) {
                return $qb;
            }

            return $qb->andWhere('1 = 0');
        }

        $orExp = $qb->expr()->orX();

        foreach ($values as $val) {

            if ($isCaseInsensitive) {
                $val = strtolower((string) $val);
            }

            $orExp->add($qb->expr()->like($expression, $qb->expr()->literal('%' . $val . '%')));
        }

        return $qb->andWhere($orExp);
    }

    /**
     * The years as integers, or null if one of them is not a year (4 digits)
     *
     * @param array<mixed>|string|int $years
     * @return list<int>|null
     */
    final public static function toValidYears(array|string|int $years): ?array
    {
        $valid = [];

        foreach ((array)$years as $year) {
            if ((!is_int($year) && !is_string($year)) || preg_match('/^\d{4}$/', (string)$year) !== 1) {
                return null;
            }

            $valid[] = (int)$year;
        }

        return array_values(array_unique($valid));
    }

    /**
     * The years are concatenated into the SQL: an invalid year gives no result
     */
    final public function whereYears(string &$sql, array|string|int|null $years = null, string $refDate = 'p.SUBMISSION_DATE'): void
    {
        if ($years === null || in_array($years, ['', '0', 0, []], true)) {
            return;
        }

        $years = self::toValidYears($years);
        $sql .= $years === null ? ' AND 1 = 0' : sprintf(' AND YEAR(%s) IN (%s)', $refDate, implode(',', $years));
    }
}
