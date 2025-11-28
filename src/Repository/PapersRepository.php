<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\Section;
use App\Entity\Volume;
use App\Entity\VolumePaper;
use App\Service\Stats;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
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
    use QueryTrait;

    public const TOTAL_ARTICLE = 'totalPublishedArticles';

    public const PAPERS_ALIAS = 'p';
    public const LOCAL_REPOSITORY = 0;

    public const AVAILABLE_FLAG_VALUES = [
        'imported' => 'imported',
        'submitted' => 'submitted'
    ];

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
     * @param bool $withoutObsolete
     * @return QueryBuilder
     */
    public function submissionsQuery(array  $filters = [],
                                     bool   $excludeTmpVersions = false,
                                     string $fieldDateToBeUsed = 'submissionDate',
                                     bool   $excludeImportedPapers = false,
                                     string $flag = null,
                                     bool   $withoutObsolete = false
    ): QueryBuilder
    {

        $qb = $this
            ->createQueryBuilder(self::PAPERS_ALIAS)
            ->select('count(distinct p.paperid)');

        $qb = $this->addQueryFilters($qb, $filters, $fieldDateToBeUsed);

        if ($withoutObsolete) {

            $qb->andWhere('p.status != :obsolete');
            $qb->setParameter('obsolete', Paper::STATUS_OBSOLETE);
        }


        $qb->andWhere('p.status != :deleted');
        $qb->setParameter('deleted', Paper::STATUS_DELETED);

        $qb->andWhere('p.status != :removed');
        $qb->setParameter('removed', Paper::STATUS_REMOVED);

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
            ->select('p.rvid, YEAR(p.when) AS year, p.repoid, p.status, count(distinct(p.paperid)) AS nbSubmissions ');

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
                    $name === AppConstants::START_AFTER_DATE ||
                    $name === AppConstants::YEAR_PARAM
                ) {

                    if (
                        $name === AppConstants::SUBMISSION_DATE
                        || $name === AppConstants::YEAR_PARAM // @see statisticStateProvider
                    ) {
                        if ($name === AppConstants::SUBMISSION_DATE) { // old stats by year @see PapersStatsProvider
                            $qb->andWhere('YEAR(' . self::PAPERS_ALIAS . '.' . $date . ') =:' . $name);
                        } else {
                            //Correction d'une erreur de type en forçant le cast de $value en tableau dans l'appel à andOrExp()
                            $this->andOrExp($qb, sprintf('YEAR(%s.%s)', self::PAPERS_ALIAS, $date), (array)$value);
                        }
                    } else {
                        $qb->andWhere(self::PAPERS_ALIAS . '.' . $date . ' >=:' . $name);
                    }

                } elseif (is_array($value)) {
                    $qb->andWhere(self::PAPERS_ALIAS . '.' . $name . ' IN (:' . $name . ')');
                } else {
                    $qb->andWhere(self::PAPERS_ALIAS . '.' . $name . ' =:' . $name);
                }

                if ($name !== AppConstants::YEAR_PARAM) {
                    $qb->setParameter($name, $value);
                }

            }
        }

        return $qb;
    }

    public function getSubmissionYearRange(array $filters = null, string $flag = null): array
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
     * @param int|null $rvId
     * @return QueryBuilder
     */


    public function getTotalArticlesBySectionOrVolumeQuery(
        string    $resourceClass = Section::class,
        int       $status = Paper::STATUS_PUBLISHED,
        int|array $identifiers = null,
        int       $rvId = null
    ): QueryBuilder
    {

        $withoutIdentifier = empty($identifiers);

        $tableId = 'sid'; // section id

        if ($resourceClass === Volume::class) {
            $tableId = 'vid'; // volume id
        }

        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);

        if (
            !$withoutIdentifier ||
            $rvId
        ) {
            $qb->select(
                sprintf('COUNT(DISTINCT(%s.paperid)) AS %s', self::PAPERS_ALIAS, self::TOTAL_ARTICLE)
            );
        } else {
            $qb->select(
                sprintf('%s.rvid, %s.%s, COUNT(DISTINCT(%s.paperid)) AS %s', self::PAPERS_ALIAS, self::PAPERS_ALIAS, $tableId, self::PAPERS_ALIAS, self::TOTAL_ARTICLE)
            );
        }

        $qb->andWhere(sprintf('%s.status =:status', self::PAPERS_ALIAS))
            ->setParameter('status', $status);

        if ($rvId) {
            $qb->andWhere(sprintf('%s.rvid = :rvid', self::PAPERS_ALIAS))
                ->setParameter('rvid', $rvId);
        }


        if (!$withoutIdentifier) { // vId(s) or sId(s) en paramètres

            if (is_int($identifiers)) {
                $identifiers = (array)$identifiers;
            }

            $this->andOrExp($qb, sprintf("%s.%s", self::PAPERS_ALIAS, $tableId), $identifiers);

        } elseif ($resourceClass === Volume::class) { // Include papers published in secondary volumes
            $qb->leftJoin(VolumePaper::class, 'vp', Join::WITH, sprintf('%s.docid = vp.docid', self::PAPERS_ALIAS));
            $qb->andWhere(sprintf('%s.%s > 0 OR vp.%s > 0 ', self::PAPERS_ALIAS, $tableId, $tableId));
        } else { // section
            $qb->andWhere(sprintf('%s.%s > 0', self::PAPERS_ALIAS, $tableId));
        }

        if (!$rvId) {
            $qb->addGroupBy(sprintf('%s.rvid', self::PAPERS_ALIAS));
            $qb->addGroupBy(sprintf('%s.%s', self::PAPERS_ALIAS, $tableId));
            $qb->addOrderBy(sprintf('%s.rvid', self::PAPERS_ALIAS), 'DESC');
        }

        return $qb;
    }


    public function getTotalArticleBySectionOrVolume(string $resourceClass = Section::class, int $status = Paper::STATUS_PUBLISHED, int|array $identifiers = null, int $rvId = null): array|float|bool|int|string|null
    {

        $resultQuery = $this->getTotalArticlesBySectionOrVolumeQuery($resourceClass, $status, $identifiers, $rvId)->getQuery();

        if (!empty($identifiers || $rvId)) {
            try {
                return [self::TOTAL_ARTICLE => $resultQuery->getSingleScalarResult()];
            } catch (NoResultException|NonUniqueResultException $e) {
                $this->logger->critical($e->getMessage());
                return [self::TOTAL_ARTICLE => null];
            }
        }

        $result = $resultQuery->getArrayResult();

        $currentRvId = null;
        $total = [];


        foreach ($result as $values) {

            if ($values['rvid'] !== $currentRvId) {
                $currentRvId = $values['rvid'];
            }

            $total[$currentRvId][self::TOTAL_ARTICLE] = isset($total[$currentRvId][self::TOTAL_ARTICLE]) ? $total[$currentRvId][self::TOTAL_ARTICLE] + (int)$values[self::TOTAL_ARTICLE] : (int)$values[self::TOTAL_ARTICLE];
        }

        return [self::TOTAL_ARTICLE => array_sum(array_column($total, self::TOTAL_ARTICLE))];
    }

    public function getTypes(array $filters = [], bool $strict = true): array
    {
        $types = [];
        $qb = $this->createQueryBuilder('p');
        $qb->select("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(p.type, '$.title')) AS type");
        $qb->andWhere("JSON_EXTRACT(p.type, '$.title') IS NOT NULL");
        $this->andWhere($qb, $filters, $strict);


        $result = array_values($qb->getQuery()->getArrayResult());

        foreach ($result as $type) {
            $type = strtolower($type['type']);
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        sort($types);

        return $types;

    }

    public function getRange(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->distinct();
        $qb->select("YEAR(p.publicationDate) AS year");

        $this->andWhere($qb, $filters);

        $qb->orderBy('year', 'DESC');

        return $this->arrayCleaner(array_column(array_values($qb->getQuery()->getResult()), 'year'));

    }


    private function andWhere(QueryBuilder $qb, array $filters = [], bool $strict = true): QueryBuilder
    {
        $isOnlyAccepted = isset($filters['isOnlyAccepted']) && $filters['isOnlyAccepted'];

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

            if ($isOnlyAccepted) {
                $this->andOrExp($qb, 'p.status', Paper::STATUS_ACCEPTED);
            } else {
                $qb->andWhere('p.status =:status')
                    ->setParameter('status', Paper::STATUS_PUBLISHED);
            }

        }


        return $qb;
    }

    /**
     * Generates a range of years between the date of first submission and the date of last publication.
     * @param int|null $rvId
     * @param string $flag
     * @return array
     */

    public function getYearRange(int $rvId = null, string $flag = 'submitted'): array
    {

        $years = [];
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->from(Paper::class, 'p');
        $qb->addSelect("YEAR(MIN(p.submissionDate)) as minYear ");
        $qb->addSelect("YEAR(Max(p.publicationDate)) as maxYear");

        $qb->andWhere("p.flag = :flag")->setParameter('flag', self::AVAILABLE_FLAG_VALUES[$flag]);

        if ($rvId) {
            $qb->andWhere("p.rvid = :rvId")->setParameter('rvId', $rvId);
        }

        $result = $qb->getQuery()->getResult();

        foreach (range($result[0]['minYear'], $result[0]['maxYear']) as $value) {

            if ($value < Stats::REF_YEAR) {
                continue;
            }

            $years[] = $value;

        }

        return $years;

    }

    /**
     * @param int $docId
     * @param int|null $rvId
     * @param string $path
     * @param bool $strict : only published
     * @return string|null
     */

    public function paperToJson(int $docId, int $rvId = null, string $path = 'all', bool $strict = true): ?string
    {
        $toJson = null;

        $qb = $this->createQueryBuilder('p');

        if ($path === 'all') {
            $qb->select('p.document');
        } else {
            $qb->select(sprintf("JSON_UNQUOTE(JSON_EXTRACT(p.document, '$.%s')) AS toJson", $path));
        }

        $qb->andWhere('p.docid = :docId')->setParameter('docId', $docId);

        if ($rvId) {
            $qb->andWhere('p.rvid = :rvId')->setParameter('rvId', $rvId);
        }

        if ($strict) {
            $qb->orWhere('p.paperid = :paperId')->setParameter('paperId', $docId);
            $qb->andWhere('p.status = :status')->setParameter('status', Paper::STATUS_PUBLISHED);
        }

        try {
            $toJson = $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);
        } catch (NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
        }

        return $toJson;

    }

    public function getSubmissionsWithoutImported(int $rvId = null, string $startAfterDate = null, array|string $years = null): int
    {

        $withoutImportedFilters = ['rvid' => $rvId, 'startAfterDate' => $startAfterDate, 'flag' => self::AVAILABLE_FLAG_VALUES['submitted'], 'year' => $years];
        try {
            return (int)$this->submissionsQuery(['is' => $withoutImportedFilters])->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            return 0;
        }

    }


    public function partialQuery(array $cols = ['rvid', 'docid', 'paperid', 'vid', 'sid', 'status', 'version', 'flag'], string $alias = self::PAPERS_ALIAS): QueryBuilder
    {

        $partialStr = implode(',', $cols);

        return
            $this->createQueryBuilder($alias)
                ->select(sprintf('partial %s.{%s}', $alias, $partialStr));
    }

    /**
     * @param int $docId
     * @return Paper|null
     */


    public function fetchPartialByDocId(int $docId): ?Paper
    {

        $partialQb = $this->partialQuery();
        $alias = $partialQb->getRootAliases()[0];

        try {
            return
                $partialQb
                    ->andWhere("$alias.docid = :val")
                    ->setParameter('val', $docId)
                    ->getQuery()
                    ->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            return null;
        }
    }

}
