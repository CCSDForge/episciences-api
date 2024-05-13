<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Service\Stats;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

}
