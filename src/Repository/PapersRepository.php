<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\Section;
use App\Entity\Volume;
use App\Service\Stats;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method Paper|null find($id, $lockMode = null, $lockVersion = null)
 * @method Paper|null findOneBy(array $criteria, array $orderBy = null)
 * @method Paper[]    findAll()
 * @method Paper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */
class PapersRepository extends ServiceEntityRepository
{
    use ToolsTrait;

    public const TOTAL_ARTICLE = 'totalPublishedArticles';

    public const PAPERS_ALIAS = 'p';
    public const LOCAL_REPOSITORY = 0;

    public const AVAILABLE_FLAG_VALUES = [
        'imported' => 'imported',
        'submitted' => 'submitted'
    ];


    // la reprise de l'existant fausse les stats

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Paper::class);
        $this->logger = $logger;
    }

    /**
     * Submissions query
     * @param array $filters
     * @param bool $excludeTmpVersions
     * @param string $fieldDateToBeUsed (default = submissionDate): l'année prise en compte est l'année de la première soumission
     * @param bool $excludeImportedPapers
     * @param string|null $flag
     * @return QueryBuilder
     */
    public function submissionsQuery(array $filters = [], bool $excludeTmpVersions = false, string $fieldDateToBeUsed = 'submissionDate', bool $excludeImportedPapers = false, string $flag = null): QueryBuilder
    {

        $qb = $this
            ->createQueryBuilder(self::PAPERS_ALIAS)
            ->select('count(p.docid)');

        $qb = $this->addQueryFilters($qb, $filters, $fieldDateToBeUsed);


        $qb->andWhere('p.status != :obsolete');
        $qb->setParameter('obsolete', Paper::STATUS_OBSOLETE);

        $qb->andWhere('p.status != :deleted');
        $qb->setParameter('deleted', Paper::STATUS_DELETED);

        if ($excludeTmpVersions) {
            $qb
                ->andWhere('p.repoid != :repoId')
                ->setParameter('repoId', self::LOCAL_REPOSITORY);
        }

        if ($excludeImportedPapers) {
            $qb
                ->andWhere('p.flag = :flag')
                ->setParameter('flag', self::AVAILABLE_FLAG_VALUES['submitted']);
        } elseif ($flag && array_key_exists($flag, self::AVAILABLE_FLAG_VALUES)) {
            $qb
                ->andWhere('p.flag = :flag')
                ->setParameter('flag', $flag);

        }

        return $qb;

    }

    /**
     * A previously submitted article that has been modified will be taken into account in the current submissions
     * @param array|null $filters
     * @param bool $excludeTmpVersions
     * @param bool $excludeImportedPapers
     * @return QueryBuilder
     */
    public function flexibleSubmissionsQueryDetails(array $filters = null, bool $excludeTmpVersions = false, bool $excludeImportedPapers = false): QueryBuilder
    {

        $qb = $this
            ->createQueryBuilder(self::PAPERS_ALIAS)
            ->select('p.rvid, YEAR(p.modificationDate) AS year, p.repoid, p.status, count(distinct(p.paperid)) AS nbSubmissions ');

        $qb = $this->addQueryFilters($qb, $filters);

        if ($excludeTmpVersions) {
            $qb
                ->andWhere('p.repoid != :repoId')
                ->setParameter('repoId', self::LOCAL_REPOSITORY);
        }

        if ($excludeImportedPapers) {
            $qb
                ->andWhere('p.flag = :flag')
                ->setParameter('flag', self::AVAILABLE_FLAG_VALUES['submitted']);
        }

        $qb->andWhere('p.status != :obsolete');
        $qb->setParameter('obsolete', Paper::STATUS_OBSOLETE);

        $qb->andWhere('p.status != :deleted');
        $qb->setParameter('deleted', Paper::STATUS_DELETED);

        $qb->orderBy('year', 'ASC');
        $qb->addOrderBy('p.rvid', 'ASC');
        $qb->addOrderBy('p.status', 'DESC');
        $qb->groupBy('p.rvid');
        $qb->groupBy('p.paperid');
        $qb->groupBy('year');
        $qb->addGroupBy('p.repoid');
        $qb->addGroupBy('p.status');

        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     * @param string $date
     * @return QueryBuilder
     */
    private function addQueryFilters(QueryBuilder $qb, array $filters, string $date = 'modificationDate'): QueryBuilder
    {

        if (array_key_exists('is', $filters) && !empty($filters['is'])) {

            foreach ($filters['is'] as $name => $value) {


                if ($name === AppConstants::WITH_DETAILS) {
                    continue;
                }

                if (
                    (null === $value || '' === $value) || // not use empty
                    !in_array($name, AppConstants::AVAILABLE_FILTERS, true)
                ) {
                    continue;
                }


                if (
                    $name === AppConstants::SUBMISSION_DATE ||
                    $name === AppConstants::START_AFTER_DATE
                ) {

                    if ($name === AppConstants::SUBMISSION_DATE) { // stats by year
                        $qb->andWhere('YEAR(' . self::PAPERS_ALIAS . '.' . $date . ') =:' . $name);
                    } else {
                        $qb->andWhere(self::PAPERS_ALIAS . '.' . $date . ' >=:' . $name);
                    }

                } elseif (is_array($value)) {
                    $qb->andWhere(self::PAPERS_ALIAS . '.' . $name . ' IN (:' . $name . ')');
                } else {
                    $qb->andWhere(self::PAPERS_ALIAS . '.' . $name . ' =:' . $name);
                }

                $qb->setParameter($name, $value);
            }
        }

        return $qb;
    }

    public function getAvailableSubmissionYears(array $filters = null, string $flag = null): array
    {

        $years = [];

        $alias = self::PAPERS_ALIAS;
        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);
        $qb->select("YEAR($alias.submissionDate) as year");
        $qb = $this->addQueryFilters($qb, $filters, 'submissionDate');

        if ($flag && array_key_exists($flag, self::AVAILABLE_FLAG_VALUES)) {

            $qb
                ->andWhere("$alias.flag = :flag")
                ->setParameter('flag', self::AVAILABLE_FLAG_VALUES[$flag]);
        }

        $qb->orderBy('year', 'ASC');
        $qb->groupBy('year');

        $result = $qb->getQuery()->getResult();

        foreach ($result as $value) {

            $years[] = $value['year'];

        }


        return array_filter($years, static function ($value) {
            return $value >= Stats::REF_YEAR;
        });


    }

    public function getAvailableRepositories(array $filters = null, $strict = true): array
    {

        $repositories = [];

        $alias = self::PAPERS_ALIAS;
        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);
        $qb->select("$alias.repoid");

        $qb = $this->addQueryFilters($qb, $filters, 'submissionDate');


        $qb->groupBy("$alias.repoid");

        $result = $qb->getQuery()->getResult();

        foreach ($result as $value) {
            $repoId = $value['repoid'];
            if ($strict) {
                $repositories[] = $repoId;
            }
        }

        return $repositories;
    }

    /**
     * @param string $resourceClass
     * @param int $status
     * @param int|array|null $identifiers
     * @param array $filters
     * @return QueryBuilder
     */


    public function getTotalArticlesBySectionOrVolumeQuery(string $resourceClass = Section::class, int $status = Paper::STATUS_PUBLISHED, int|array $identifiers = null, array $filters = []): QueryBuilder
    {

        $withoutIdentifier = empty($identifiers);

        $tableId = 'sid';

        if ($resourceClass === Volume::class) {
            $tableId = 'vid';
        }

        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);

        if (!$withoutIdentifier) {

            if (is_int($identifiers)) {
                $identifiers = (array)$identifiers;
            }

            $qb->select(sprintf('COUNT(DISTINCT(%s.paperid)) AS %s', self::PAPERS_ALIAS, self::TOTAL_ARTICLE));
        } else {
            $qb->select(sprintf('%s.rvid, %s.%s, COUNT(DISTINCT(%s.paperid)) AS %s', self::PAPERS_ALIAS, self::PAPERS_ALIAS, $tableId, self::PAPERS_ALIAS, self::TOTAL_ARTICLE));
        }

        $qb->andWhere(sprintf('%s.status =:status', self::PAPERS_ALIAS))
            ->andWhere(sprintf('%s.%s != 0', self::PAPERS_ALIAS, $tableId))
            ->setParameter('status', $status);


        if (!$withoutIdentifier) {

            $orExp = $qb->expr()->orX();

            foreach ($identifiers as $val) {
                $val = (int)$val;
                $orExp->add($qb->expr()->eq(sprintf("%s.%s", self::PAPERS_ALIAS, $tableId), $val));
            }

            $qb->andWhere($orExp);
        } else {
            $qb->addGroupBy(sprintf('%s.rvid', self::PAPERS_ALIAS));
            $qb->addGroupBy(sprintf('%s.%s', self::PAPERS_ALIAS, $tableId));
            $qb->addOrderBy(sprintf('%s.rvid', self::PAPERS_ALIAS), 'DESC');
        }

        return $qb;

    }


    public function getTotalArticleBySectionOrVolume(string $resourceClass = Section::class, int $status = Paper::STATUS_PUBLISHED, int|array $identifiers = null, int $rvId = null, array $filters = []): array|float|bool|int|string|null
    {

        $resultQuery = $this->getTotalArticlesBySectionOrVolumeQuery($resourceClass, $status, $identifiers, $filters)->getQuery();

        if (!empty($identifiers)) {
            try {
                return [self::TOTAL_ARTICLE => $resultQuery->getSingleScalarResult()];
            } catch (NoResultException|NonUniqueResultException $e) {
                $this->logger->critical($e->getMessage());
                return [self::TOTAL_ARTICLE => null];
            }
        }

        $result = $resultQuery->getArrayResult();

        $assoc[self::TOTAL_ARTICLE] = 0;

        foreach ($result as $values) {

            $key = $resourceClass === Section::class ? 'sid' : 'vid';

            $currentRvId = $values['rvid'] ?? null;

            if ($currentRvId) {
                $assoc[$currentRvId][self::TOTAL_ARTICLE] = $assoc[$currentRvId][self::TOTAL_ARTICLE] ?? 0;
                $assoc[self::TOTAL_ARTICLE] += $values[self::TOTAL_ARTICLE]; // all platform
                $assoc[$currentRvId][self::TOTAL_ARTICLE] += $values[self::TOTAL_ARTICLE];
                $assoc[$currentRvId][$values[$key]][self::TOTAL_ARTICLE] = $values[self::TOTAL_ARTICLE];
            } else {
                $assoc[$values[$key]] = $values[self::TOTAL_ARTICLE];
            }

        }

        return $rvId ? $assoc[$rvId] : $assoc;
    }

    public function getTypes(array $filters = [], bool $strict = true): array
    {
        $types = [];
        $qb = $this->createQueryBuilder('p');
        $qb->select("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(p.type, '$.title')) AS types");
        $qb->andWhere("JSON_EXTRACT(p.type, '$.title') IS NOT NULL");
        $this->andWhere($qb, $filters, $strict);


        $result = array_values($qb->getQuery()->getArrayResult());

        foreach ($result as $type) {
            $type = strtolower($type['types']);
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        natcasesort($types);

        return $types;

    }

    public function getRange(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->distinct();
        $qb->select("YEAR(p.publicationDate) AS year");

        $this->andWhere($qb, $filters);

        $qb->orderBy('year', 'DESC');

        return $qb->getQuery()->getResult();

    }


    private function andWhere(QueryBuilder $qb, array $filters = [], bool $strict = true): QueryBuilder
    {

        foreach ($filters as $key => $value) {

            if ($key !== 'rvid' && $key !== 'sid' && $key !== 'vid') {
                continue;
            }

            $value = (int)$value;

            if ($value) {
                if ($key === 'rvid') {
                    $qb->andWhere('p.rvid = :rvId');
                    $qb->setParameter('rvId', $value);
                } elseif ($key === 'vid') {

                    $qb->andWhere('p.vid = :vId');
                    $qb->setParameter('vId', $value);
                } elseif ($key === 'sid') {
                    $qb->andWhere('p.sid = :sId');
                    $qb->setParameter('sId', $value);

                }

            }

        }

        if ($strict) {
            $qb->andWhere('p.status =:status')
                ->setParameter('status', Paper::STATUS_PUBLISHED);
        }


        return $qb;
    }

}
