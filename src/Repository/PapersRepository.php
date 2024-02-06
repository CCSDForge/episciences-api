<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\PaperLog;
use App\Entity\Papers;
use App\Resource\SubmissionOutput;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method Papers|null find($id, $lockMode = null, $lockVersion = null)
 * @method Papers|null findOneBy(array $criteria, array $orderBy = null)
 * @method Papers[]    findAll()
 * @method Papers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */
class PapersRepository extends ServiceEntityRepository
{
    use ToolsTrait;

    public const AVAILABLE_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::PAPER_FLAG, AppConstants::PAPER_STATUS];
    public const PAPERS_ALIAS = 'p';
    public const LOCAL_REPOSITORY = 0;

    public const AVAILABLE_FLAG_VALUES = [
        'imported' => 'imported',
        'submitted' => 'submitted'
    ];

    public const SUBMISSIONS_BY_YEAR = 'submissionsByYear';

    public const TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR = 'acceptedSubmittedSameYear';
    public const TOTAL_ACCEPTED = 'accepted';
    public const TOTAL_REFUSED = 'refused';
    public const ACCEPTANCE_RATE = 'acceptanceRate'; // TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR / submissions by year
    public const MORE_DETAILS = 'moreDetailsFromModifDate';

    public const REF_YEAR = '2013'; // la reprise de l'existant fausse les stats

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Papers::class);
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
        $qb->setParameter('obsolete', Papers::STATUS_OBSOLETE);

        $qb->andWhere('p.status != :deleted');
        $qb->setParameter('deleted', Papers::STATUS_DELETED);

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
    private function flexibleSubmissionsQueryDetails(array $filters = null, bool $excludeTmpVersions = false, bool $excludeImportedPapers = false): QueryBuilder
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
        $qb->setParameter('obsolete', Papers::STATUS_OBSOLETE);

        $qb->andWhere('p.status != :deleted');
        $qb->setParameter('deleted', Papers::STATUS_DELETED);

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
     * @param array $filters
     * @param bool $excludeTmpVersions
     * @return SubmissionOutput
     */
    public function getSubmissionsStat(array $filters = [], bool $excludeTmpVersions = false): SubmissionOutput
    {

        $rvId = $filters['is']['rvid'] ?? null;
        $status = $filters['is']['status'] ?? null; // submitted: $status = 0
        $year = (array_key_exists('submissionDate', $filters['is']) && !empty($filters['is']['submissionDate'])) ?
            $filters['is']['submissionDate'] : null;
        $repoId = $filters['is']['repoid'] ?? null; // tmp version: $repoId = 0
        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);

        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        $details = null;

//        if (null === $rvId) {
//            $allSubmissionsQb = $this->submissionsQuery();
//        } else {
//
//            $allSubmissionsQb = $this->submissionsQuery([
//                'is' => ['rvid' => $rvId]
//            ]);
//        }

        try {
            $nbSubmissions = (int)$this->submissionsQuery($filters)->getQuery()->getSingleScalarResult();

            //$allSubmissions = (int)$allSubmissionsQb->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $nbSubmissions = null;
            //$allSubmissions = null;
            $this->logger->error($e->getMessage());
        }


        if ($withDetails) {

            //$percentage = ($allSubmissions) ? round($nbSubmissions / $allSubmissions * 100, 2) : null;
            //$details = ['allSubmissions' => $allSubmissions, 'percentage' => $percentage];
            $submissionsStats = $this->flexibleSubmissionsQueryDetails($filters, $excludeTmpVersions)->
            getQuery()->getArrayResult();

            if (!empty($submissionsStats)) {

                if (null !== $rvId && !$year && null === $status && null === $repoId) { // by review
                    $rvIdResult = $this->applyFilterBy($submissionsStats, 'rvid', $rvId);
                    $details[self::MORE_DETAILS] = $this->reformatData($rvIdResult[$rvId]);
                    $repositories = $this->getAvailableRepositories($filters);

                    foreach ($this->getAvailableSubmissionYears($filters) as $year) { // pour le dashboard
                        try {
                            $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] = $this->
                            submissionsQuery(['is' => ['rvid' => $rvId, 'submissionDate' => $year]])->
                            getQuery()->
                            getSingleScalarResult();

                            $details[self::SUBMISSIONS_BY_YEAR][$year]['publications'] = $this->
                            submissionsQuery(
                                [
                                    'is' => [
                                        'rvid' => $rvId,
                                        'status' => Papers::STATUS_PUBLISHED,
                                        'submissionDate' => $year
                                    ]
                                ], false, 'publicationDate'
                            )->
                            getQuery()->getSingleScalarResult();


                            $plRepo = $this->getEntityManager()->getRepository(PaperLog::class);

                            $totalNumberOfPapersAcceptedSubmittedSameYear = $plRepo->getTotalNumberOfPapersByStatus($rvId);

                            $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] =
                                isset($totalNumberOfPapersAcceptedSubmittedSameYear[$rvId][$year]) ?
                                    $totalNumberOfPapersAcceptedSubmittedSameYear[$rvId][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] :
                                    0;

                            $details[self::SUBMISSIONS_BY_YEAR][$year][self::ACCEPTANCE_RATE] = $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] ?
                                round($details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] / $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] * 100, 2) : 0;


                            foreach ($repositories as $repoId) {
                                $details['submissionsByRepo'][$year][$repoId]['submissions'] = $this->
                                submissionsQuery(
                                    ['is' => ['rvid' => $rvId, 'submissionDate' => $year, 'repoid' => $repoId]
                                    ])->getQuery()->getSingleScalarResult();
                            }
                        } catch (NoResultException|NonUniqueResultException $e) {
                            $this->logger->error($e->getMessage());
                        }
                    }


                } elseif (null === $rvId && $year && null === $status && $repoId === null) { // by year
                    $yearResult = $this->applyFilterBy($submissionsStats, 'year', $year);
                    $details[self::MORE_DETAILS] = $yearResult;


                } elseif (null === $rvId && !$year && null === $status && $repoId !== null) { // by $repoId
                    $repoResult = $this->applyFilterBy($submissionsStats, 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $repoResult;


                } elseif (null === $rvId && !$year && null !== $status && $repoId === null) { // by $status
                    $statusResult = $this->applyFilterBy($submissionsStats, 'status', (string)$status);
                    $details[self::MORE_DETAILS] = $statusResult;


                } elseif ($year && $rvId && null === $status && $repoId === null) { // $review & $year
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);


                } elseif (!$year && $rvId && null === $status && $repoId !== null) { // $review & $repoId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'repoid', (string)$repoId);


                } elseif (!$year && $rvId && null !== $status && $repoId === null) { // $review & $status
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'status', (string)$status);


                } elseif ($year && $rvId && null === $status && $repoId !== null) { // $review & $year & repoId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);


                } elseif ($year && $rvId && null !== $status && $repoId === null) { // $review & $year & $status
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'status', (string)$status);


                } elseif ($year && !$rvId && null !== $status && $repoId === null) { //$year & $status
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'status', (string)$status);


                } elseif ($year && !$rvId && null === $status && $repoId !== null) { //$year & $repoId
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);


                } elseif (!$year && !$rvId && null !== $status && $repoId !== null) { //$repoId & $status
                    $repoDetails = $this->applyFilterBy($submissionsStats, 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif (!$year && $rvId && null !== $status && $repoId !== null) { //$repoId & $status & $rvId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $repoDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif ($year && !$rvId && null !== $status && $repoId !== null) { // $year && $repoId && $status
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $repoDetails = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif ($year && $rvId && null !== $status && $repoId !== null) { // $year && $rvId && $repoId & $year
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $repoDetails = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } else {
                    $details[self::MORE_DETAILS] = $this->reformatData($submissionsStats);
                }

            }

        }

        return (new SubmissionOutput())->
        setAvailableFilters(self::AVAILABLE_FILTERS)->
        setRequestedFilters($filters['is'])->
        setName('nbSubmissions')->
        setValue($nbSubmissions)->
        setDetails($details);
    }

    private function reformatData(array $array): array
    {
        $year = null;
        $rvId = null;
        $repoId = null;
        $status = null;
        $result = [];

        foreach ($array as $value) {
            foreach ($value as $k => $v) {
                if ($k === 'rvid' && $v !== $rvId) {
                    $rvId = $v;
                }

                if ($k === 'year' && $v !== $year) {
                    $year = $v;
                }

                if ($k === 'repoid' && $v !== $repoId) {
                    $repoId = $v;
                }

                if ($k === 'status' && $v !== $status) {
                    $status = $v;
                }

                if ($k === 'nbSubmissions') {
                    if (!$rvId) {
                        $result[$year][$repoId][Papers::STATUS_DICTIONARY[$status]][$k] = $v;
                    } else {
                        $result[$rvId][$year][$repoId][Papers::STATUS_DICTIONARY[$status]][$k] = $v;
                    }
                }
            }
        }

        return $result;
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
            return $value >= self::REF_YEAR;
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
