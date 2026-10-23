<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
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
use Doctrine\DBAL\ArrayParameterType;
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
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\Paper>
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

    public function __construct(ManagerRegistry $registry, private LoggerInterface $logger)
    {
        parent::__construct($registry, Paper::class);
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
                                     ?string $flag = null,
                                     bool   $withoutObsolete = false
    ): QueryBuilder
    {

        $qb = $this
            ->createQueryBuilder(self::PAPERS_ALIAS)
            ->select('count(distinct p.paperid)');

        $qb = $this->addQueryFilters($qb, $filters, $fieldDateToBeUsed);

        if (empty($filters['is']['rvid'])) {
            $this->andActiveJournals($qb, sprintf('%s.rvid', self::PAPERS_ALIAS));
        }

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
    public function flexibleSubmissionsQueryDetails(?array $filters = null, bool $excludeTmpVersions = false, bool $excludeImportedPapers = false): QueryBuilder
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

        $qb->orderBy('year', \SortDirection::Ascending);
        $qb->addOrderBy('p.rvid', \SortDirection::Ascending);
        $qb->addOrderBy('p.status', \SortDirection::Descending);
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
    private function addQueryFilters(QueryBuilder $qb, array $filters, string $date = 'submissionDate'): QueryBuilder
    {
        if (empty($filters['is'])) {
            return $qb;
        }

        $dateYearFilters = [AppConstants::SUBMISSION_DATE, AppConstants::YEAR_PARAM];
        $dateAfterFilters = [AppConstants::START_AFTER_DATE];

        foreach ($filters['is'] as $name => $value) {
            if ($name === AppConstants::WITH_DETAILS) {
                continue;
            }

            $isEmptyValue = $value === null || $value === '';
            $isAllowedFilter = in_array($name, AppConstants::AVAILABLE_FILTERS, true);

            if ($isEmptyValue || !$isAllowedFilter) {
                continue;
            }

            // the submission date of a paper is the one of its first version: common to all its versions
            if ($date === 'submissionDate' && (in_array($name, $dateYearFilters, true) || in_array($name, $dateAfterFilters, true))) {
                $this->andFirstSubmission($qb, $name, $value);
                continue;
            }

            if (in_array($name, $dateYearFilters, true)) {
                if ($name === AppConstants::SUBMISSION_DATE) { // old stats by year @see PapersStatsProvider
                    $qb->andWhere(sprintf('YEAR(%s.%s) =:%s', self::PAPERS_ALIAS, $date, $name));
                } else { // YEAR_PARAM
                    $this->andOrExp($qb, sprintf('YEAR(%s.%s)', self::PAPERS_ALIAS, $date), (array)$value);
                }
            } elseif (in_array($name, $dateAfterFilters, true)) {
                $qb->andWhere(sprintf('%s.%s >=:%s', self::PAPERS_ALIAS, $date, $name));
            } elseif (is_array($value)) {
                $qb->andWhere(sprintf('%s.%s IN (:%s)', self::PAPERS_ALIAS, $name, $name));
            } else {
                $qb->andWhere(sprintf('%s.%s =:%s', self::PAPERS_ALIAS, $name, $name));
            }

            if ($name !== AppConstants::YEAR_PARAM) {
                $qb->setParameter($name, $value);
            }
        }

        return $qb;
    }

    /**
     * Filters on the first submission of the paper (the oldest submission date of its versions): a paper whose versions
     * were submitted in different years is counted once, in the year of its first submission.
     */
    private function andFirstSubmission(QueryBuilder $qb, string $filter, mixed $value): void
    {
        $alias = 'firstSubmission' . ucfirst($filter);
        $condition = match ($filter) {
            AppConstants::SUBMISSION_DATE => sprintf('YEAR(MIN(%s.submissionDate)) = :%s', $alias, $alias),
            AppConstants::YEAR_PARAM => sprintf('YEAR(MIN(%s.submissionDate)) IN (:%s)', $alias, $alias),
            default => sprintf('MIN(%s.submissionDate) >= :%s', $alias, $alias), // startAfterDate
        };

        $qb
            ->andWhere(sprintf('%1$s.paperid IN (SELECT %2$s.paperid FROM %3$s %2$s GROUP BY %2$s.paperid HAVING %4$s)', self::PAPERS_ALIAS, $alias, Paper::class, $condition))
            ->setParameter($alias, $filter === AppConstants::YEAR_PARAM ? array_map('intval', (array)$value) : $value);
    }

    /**
     * Year of the first submission of each paper (the oldest submission date of its versions)
     *
     * @param int[] $paperIds
     * @return array<int, int> year indexed by PAPERID
     */
    public function getFirstSubmissionYears(array $paperIds): array
    {
        $years = [];

        foreach (array_chunk(array_values(array_unique($paperIds)), 1000) as $chunk) {
            $rows = $this->createQueryBuilder(self::PAPERS_ALIAS)
                ->select(sprintf('%1$s.paperid AS paperid, YEAR(MIN(%1$s.submissionDate)) AS year', self::PAPERS_ALIAS))
                ->where(sprintf('%s.paperid IN (:paperIds)', self::PAPERS_ALIAS))
                ->setParameter('paperIds', $chunk)
                ->groupBy('paperid')
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $years[(int)$row['paperid']] = (int)$row['year'];
            }
        }

        return $years;
    }

    /**
     * @param int[] $paperIds
     * @return array<int, int> number of papers by year of first submission
     */
    private function countByFirstSubmissionYear(array $paperIds): array
    {
        $totals = [];

        foreach ($this->getFirstSubmissionYears($paperIds) as $year) {
            $totals[$year] = ($totals[$year] ?? 0) + 1;
        }

        ksort($totals);

        return $totals;
    }

    public function getSubmissionYearRange(?array $filters = null, ?string $flag = null): array
    {
        $alias = self::PAPERS_ALIAS;
        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);
        $qb->select("DISTINCT $alias.paperid AS paperid");
        $qb = $this->addQueryFilters($qb, $filters);

        if ($flag && array_key_exists($flag, self::AVAILABLE_FLAG_VALUES)) {

            $qb
                ->andWhere("$alias.flag = :flag")
                ->setParameter('flag', self::AVAILABLE_FLAG_VALUES[$flag]);
        }

        // years of first submission of the papers
        $years = array_keys($this->countByFirstSubmissionYear(array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'paperid'))));

        return array_values(array_filter($years, static fn($value): bool => $value >= Stats::REF_YEAR));
    }

    public function getAvailableRepositories(?array $filters = null, $strict = true): array
    {

        $repositories = [];

        $alias = self::PAPERS_ALIAS;
        $qb = $this->createQueryBuilder(self::PAPERS_ALIAS);
        $qb->select("$alias.repoid");

        $qb = $this->addQueryFilters($qb, $filters);
        // the local repository holds the temporary versions, not a submission: see countSubmissionsByYearAndRepository()
        $qb->andWhere("$alias.repoid != :localRepository")->setParameter('localRepository', self::LOCAL_REPOSITORY);


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
        int|array|null $identifiers = null,
        ?int       $rvId = null
    ): QueryBuilder
    {

        $withoutIdentifier = in_array($identifiers, [0, [], null], true);

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

            if ($resourceClass === Volume::class) { // Include papers published in secondary volumes
                $qb->leftJoin(VolumePaper::class, 'vp', Join::ON, sprintf('%s.docid = vp.docid', self::PAPERS_ALIAS));
                $qb->andWhere(sprintf('%1$s.%2$s IN (:identifiers) OR vp.%2$s IN (:identifiers)', self::PAPERS_ALIAS, $tableId))
                    ->setParameter('identifiers', array_map('intval', $identifiers), ArrayParameterType::INTEGER);
            } else {
                $this->andOrExp($qb, sprintf("%s.%s", self::PAPERS_ALIAS, $tableId), $identifiers);
            }

        } elseif ($resourceClass === Volume::class) { // Include papers published in secondary volumes
            $qb->leftJoin(VolumePaper::class, 'vp', Join::ON, sprintf('%s.docid = vp.docid', self::PAPERS_ALIAS));
            $qb->andWhere(sprintf('%s.%s > 0 OR vp.%s > 0 ', self::PAPERS_ALIAS, $tableId, $tableId));
        } else { // section
            $qb->andWhere(sprintf('%s.%s > 0', self::PAPERS_ALIAS, $tableId));
        }

        // only for the grouped select: with identifiers, a single total is counted (even without a journal)
        if ($withoutIdentifier && !$rvId) {
            $qb->addGroupBy(sprintf('%s.rvid', self::PAPERS_ALIAS));
            $qb->addGroupBy(sprintf('%s.%s', self::PAPERS_ALIAS, $tableId));
            $qb->addOrderBy(sprintf('%s.rvid', self::PAPERS_ALIAS), \SortDirection::Descending);
        }

        return $qb;
    }


    public function getTotalArticleBySectionOrVolume(string $resourceClass = Section::class, int $status = Paper::STATUS_PUBLISHED, int|array|null $identifiers = null, ?int $rvId = null): array|float|bool|int|string|null
    {

        $resultQuery = $this->getTotalArticlesBySectionOrVolumeQuery($resourceClass, $status, $identifiers, $rvId)->getQuery();

        if ($identifiers || $rvId) {
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
            $type = strtolower((string) $type['type']);
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

        $qb->orderBy('year', \SortDirection::Descending);

        return $this->arrayCleaner(array_column(array_values($qb->getQuery()->getResult()), 'year'));

    }


    private function andWhere(QueryBuilder $qb, array $filters = [], bool $strict = true): QueryBuilder
    {
        $isOnlyAccepted = isset($filters['isOnlyAccepted']) && $filters['isOnlyAccepted'];

        foreach ($filters as $key => $value) {

            if (!in_array($key, ['rvid', 'sid', 'vid'], true)) {
                continue;
            }

            $value = (int)$value;

            if ($value !== 0) {
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
     * Generates a range of years between the date of first submission and the date of last submission.
     * @param int|null $rvId
     * @param string $flag
     * @return array
     */

    public function getYearRange(?int $rvId = null, string $flag = 'submitted'): array
    {

        $years = [];
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->from(Paper::class, 'p');
        $qb->addSelect("YEAR(MIN(p.submissionDate)) as minYear ");
        $qb->addSelect("GREATEST(
        YEAR(MAX(p.submissionDate)),
        YEAR(COALESCE(MAX(p.publicationDate), MAX(p.submissionDate)))
        ) as maxYear"
        );

        $qb->andWhere("p.flag = :flag")->setParameter('flag', self::AVAILABLE_FLAG_VALUES[$flag]);

        if ($rvId) {
            $qb->andWhere("p.rvid = :rvId")->setParameter('rvId', $rvId);
        } else {
            $this->andActiveJournals($qb, 'p.rvid');
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

    public function paperToJson(int $docId, ?int $rvId = null, string $path = 'all', bool $strict = true): ?string
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

    /**
     * Number of imported submissions by year of submission
     * @param array<string, mixed> $filters see submissionsQuery()
     * @return array<int, int> year => number of imported submissions
     */
    public function getImportedByYear(array $filters = []): array
    {
        return $this->countByFirstSubmissionYear($this->getSubmissionPaperIds($filters, self::AVAILABLE_FLAG_VALUES['imported']));
    }

    /**
     * Same count as submissionsQuery(), by year of first submission of the papers (two queries for all the years)
     *
     * @param array<string, mixed> $filters see submissionsQuery()
     * @return array<int, int> number of submissions indexed by year
     */
    public function countSubmissionsByYear(array $filters = []): array
    {
        return $this->countByFirstSubmissionYear($this->getSubmissionPaperIds($filters));
    }

    /**
     * Same count as submissionsQuery(), for each journal: one query for all the journals
     *
     * @param array<string, mixed> $filters see submissionsQuery()
     * @return array<int, int> number of submissions indexed by journal identifier (rvid); the journals without submission are not returned
     */
    public function countSubmissionsByJournal(array $filters = []): array
    {
        $qb = $this->submissionsQuery($filters)
            ->select(sprintf('%1$s.rvid AS rvid, COUNT(DISTINCT %1$s.paperid) AS total', self::PAPERS_ALIAS))
            ->groupBy('rvid');

        $totals = [];

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $totals[(int)$row['rvid']] = (int)$row['total'];
        }

        return $totals;
    }

    /**
     * Same count as submissionsQuery(), by year of first submission and by repository (two queries for all the years);
     * the temporary versions (local repository) are ignored, a paper is counted in the repository of its first other version
     *
     * @param array<string, mixed> $filters see submissionsQuery()
     * @return array<int, array<int, int>> number of submissions indexed by year, then by repository identifier
     */
    public function countSubmissionsByYearAndRepository(array $filters = []): array
    {
        // the local repository holds the temporary versions: a paper is counted in the repository of its first other version
        $rows = $this->submissionsQuery($filters)
            ->select(sprintf('%1$s.paperid AS paperid, %1$s.docid AS docid, %1$s.repoid AS repoid', self::PAPERS_ALIAS))
            ->andWhere(sprintf('%s.repoid != :localRepository', self::PAPERS_ALIAS))
            ->setParameter('localRepository', self::LOCAL_REPOSITORY)
            ->getQuery()
            ->getScalarResult();

        $firstVersions = []; // paperid => [docid, repoid]

        foreach ($rows as $row) {
            $paperId = (int)$row['paperid'];

            if (!isset($firstVersions[$paperId]) || (int)$row['docid'] < $firstVersions[$paperId][0]) {
                $firstVersions[$paperId] = [(int)$row['docid'], (int)$row['repoid']];
            }
        }

        $totals = [];

        foreach ($this->getFirstSubmissionYears(array_keys($firstVersions)) as $paperId => $year) {
            $repoId = $firstVersions[$paperId][1];
            $totals[$year][$repoId] = ($totals[$year][$repoId] ?? 0) + 1;
        }

        return $totals;
    }

    /**
     * Numbers of submissions, imported and submitted papers in a single query (GET /api/statistics/).
     *
     * Same results as the three former queries: the journal, year and date filters apply to the three
     * numbers; the "status" filter to the total and the imported papers; the "flag" filter to the total
     * only (the submitted papers ignore both, see getSubmissionsWithoutImported()).
     *
     * @param array<string, mixed> $filters filters of submissionsQuery() (without the "is" level)
     *
     * @return array{total: int, imported: int, submitted: int}
     */
    public function countSubmissionsByFlag(array $filters = []): array
    {
        $result = $this->countSubmissionsByFlagQuery($filters)->getQuery()->getSingleResult();

        return ['total' => (int)$result['total'], 'imported' => (int)$result['imported'], 'submitted' => (int)$result['submitted']];
    }

    /**
     * @param array<string, mixed> $filters see countSubmissionsByFlag()
     */
    public function countSubmissionsByFlagQuery(array $filters = []): QueryBuilder
    {
        $alias = self::PAPERS_ALIAS;
        $common = array_intersect_key($filters, array_flip(['rvid', AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE]));
        $qb = $this->submissionsQuery(['is' => $common]);

        $totalConditions = [];
        $importedConditions = [sprintf('%s.flag = :importedFlag', $alias)];
        $qb->setParameter('importedFlag', self::AVAILABLE_FLAG_VALUES['imported']);

        if (!empty($filters['status'])) {
            $statusCondition = sprintf('%s.status IN (:statusFilter)', $alias);
            $totalConditions[] = $statusCondition;
            $importedConditions[] = $statusCondition;
            $qb->setParameter('statusFilter', (array)$filters['status']);
        }

        if (!empty($filters['flag'])) {
            $totalConditions[] = sprintf('%s.flag = :flagFilter', $alias);
            $qb->setParameter('flagFilter', $filters['flag']);
        }

        $countIf = static fn(array $conditions): string => $conditions === []
            ? sprintf('COUNT(DISTINCT %s.paperid)', $alias)
            : sprintf('COUNT(DISTINCT CASE WHEN %s THEN %s.paperid ELSE NULL END)', implode(' AND ', $conditions), $alias);

        $qb
            ->select(sprintf('%s AS total', $countIf($totalConditions)))
            ->addSelect(sprintf('%s AS imported', $countIf($importedConditions)))
            ->addSelect(sprintf('COUNT(DISTINCT CASE WHEN %1$s.flag = :submittedFlag THEN %1$s.paperid ELSE NULL END) AS submitted', $alias))
            ->setParameter('submittedFlag', self::AVAILABLE_FLAG_VALUES['submitted']);

        return $qb;
    }

    public function getSubmissionsWithoutImported(?int $rvId = null, ?string $startAfterDate = null, array|string|null $years = null): int
    {

        $withoutImportedFilters = ['rvid' => $rvId, 'startAfterDate' => $startAfterDate, 'flag' => self::AVAILABLE_FLAG_VALUES['submitted'], 'year' => $years];
        try {
            return (int)$this->submissionsQuery(['is' => $withoutImportedFilters])->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            return 0;
        }

    }


    /**
     * Submissions still awaiting an editorial decision: neither published, accepted nor refused (decisions read from the history,
     * as PaperLogRepository::acceptedQuery and refusedQuery, so that an article is not counted twice), deleted nor removed;
     * an abandoned article is only paused, so it is still awaiting a decision. Counted by article (PAPERID, common to all its versions), imported articles excluded.
     * The year and the start date apply to the submission date.
     */
    public function getSubmissionsAwaitingDecision(?int $rvId = null, ?string $startAfterDate = null, array|string|null $years = null): int
    {
        try {
            return (int)$this->awaitingDecisionQuery($rvId, $startAfterDate, $years)->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $this->logger->critical($e->getMessage());
            return 0;
        }
    }

    /**
     * Same count as getSubmissionsAwaitingDecision(), by year of first submission (two queries for all the years)
     *
     * @return array<int, int> indexed by year; the years without submission awaiting a decision are not returned
     */
    public function getSubmissionsAwaitingDecisionByYear(?int $rvId = null, ?string $startAfterDate = null): array
    {
        return $this->countByFirstSubmissionYear($this->getSubmissionsAwaitingDecisionPaperIds($rvId, $startAfterDate));
    }

    /**
     * Identifiers (PAPERID) of the papers counted by submissionsQuery()
     *
     * @param array<string, mixed> $filters see submissionsQuery()
     * @return list<int> sorted
     */
    public function getSubmissionPaperIds(array $filters = [], ?string $flag = null, string $fieldDateToBeUsed = 'submissionDate'): array
    {
        return $this->paperIds($this->submissionsQuery($filters, false, $fieldDateToBeUsed, false, $flag));
    }

    /**
     * The DOCID of the latest version of each paper
     *
     * @param int[] $paperIds
     * @return array<int, int> DOCID indexed by PAPERID
     */
    public function getLatestDocIds(array $paperIds): array
    {
        $docIds = [];

        foreach (array_chunk(array_values(array_unique($paperIds)), 1000) as $chunk) {
            $rows = $this->createQueryBuilder(self::PAPERS_ALIAS)
                ->select(sprintf('%1$s.paperid AS paperid, MAX(%1$s.docid) AS docid', self::PAPERS_ALIAS))
                ->where(sprintf('%s.paperid IN (:paperIds)', self::PAPERS_ALIAS))
                ->setParameter('paperIds', $chunk)
                ->groupBy('paperid')
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $docIds[(int)$row['paperid']] = (int)$row['docid'];
            }
        }

        return $docIds;
    }

    /**
     * Identifiers (PAPERID) of the papers counted by getSubmissionsAwaitingDecision()
     *
     * @return list<int> sorted
     */
    public function getSubmissionsAwaitingDecisionPaperIds(?int $rvId = null, ?string $startAfterDate = null, array|string|null $years = null): array
    {
        return $this->paperIds($this->awaitingDecisionQuery($rvId, $startAfterDate, $years));
    }

    /**
     * @return list<int> sorted
     */
    private function paperIds(QueryBuilder $qb): array
    {
        $qb->select(sprintf('DISTINCT %s.paperid AS paperid', self::PAPERS_ALIAS))->orderBy('paperid');

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'paperid'));
    }

    private function awaitingDecisionQuery(?int $rvId, ?string $startAfterDate, array|string|null $years = null): QueryBuilder
    {
        $filters = ['rvid' => $rvId, 'startAfterDate' => $startAfterDate, 'flag' => self::AVAILABLE_FLAG_VALUES['submitted'], 'year' => $years];

        // deleted and removed papers are already excluded
        $qb = $this->submissionsQuery(['is' => $filters], withoutObsolete: true)
            ->andWhere('p.status NOT IN (:decidedStatuses)')
            ->setParameter('decidedStatuses', [Paper::STATUS_PUBLISHED, Paper::STATUS_REFUSED]);

        $decision = $this->getEntityManager()->createQueryBuilder()
            ->select('decisionLog.logid')
            ->from(PaperLog::class, 'decisionLog')
            ->where('decisionLog.paperid = p.paperid')
            ->andWhere('decisionLog.status IN (:decisionStatuses)');

        return $qb->andWhere($qb->expr()->not($qb->expr()->exists($decision->getDQL())))
            ->setParameter('decisionStatuses', [...PaperLogRepository::ACCEPTED_STATUSES, PaperLogRepository::REFUSED_STATUS]);
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
