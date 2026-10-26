<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\PaperLog;
use App\Entity\Paper;
use App\Entity\Review;
use App\Service\Stats;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @method PaperLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaperLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaperLog[]    findAll()
 * @method PaperLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\PaperLog>
 */
class PaperLogRepository extends ServiceEntityRepository
{
    /** Statuses of an accepted article in its history (see acceptedQuery) */
    public const array ACCEPTED_STATUSES = [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED];
    /** Figures whose papers can be listed (see getPaperIds()) */
    public const array PAPER_IDS_FIGURES = ['accepted' => 'accepted', 'refused' => 'refused', 'published' => 'published', 'accepted-not-yet-published' => 'accepted-not-yet-published'];
    /** Status of a refused article in its history (see refusedQuery) */
    public const int REFUSED_STATUS = Paper::STATUS_REFUSED;

    use QueryTrait;
    use ToolsTrait;

    public const DELAY = 'delay';
    public const DEFAULT_UNIT = 'DAY';
    /** Units of the delays (TIMESTAMPDIFF) */
    public const array DELAY_UNITS = ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'];

    public const AVAILABLE_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE];

    public function __construct(ManagerRegistry $registry, private LoggerInterface $logger)
    {
        parent::__construct($registry, PaperLog::class);
    }

    /**
     * Delay of each article between its first submission and its latest acceptance (or publication), imported articles excluded:
     * rows [year (of the latest acceptance/publication), rvid, delay, PAPERID, min_date, max_date].
     * The average and the median delays are computed from these rows (see Stats).
     * @param string $unit one of DELAY_UNITS (an unsupported unit falls back to WEEK)
     * @param int $latestStatus
     * @param string|null $startDate the articles submitted before this date (Y-m-d) are left out; ignored if invalid
     * @param string|int|array<int|string>|null $years years of the latest acceptance (or publication); an invalid year gives no result
     * @param int|null $rvId without journal, the active journals
     * @return list<array<string, mixed>>|null null on a database error
     */
    public function getArticleDelays(string $unit = self::DEFAULT_UNIT, int $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED, ?string $startDate = null, string|int|array|null $years = null, ?int $rvId = null): ?array
    {
        $unit = strtoupper($unit);

        // the unit is a keyword of the query: only a supported unit is accepted
        if (!in_array($unit, self::DELAY_UNITS, true)) {
            $unit = 'WEEK';
        }

        $validYears = null;

        if (!($years === null || in_array($years, ['', '0', 0, []], true))) {
            $validYears = self::toValidYears($years);

            if ($validYears === null) {
                return [];
            }
        }

        // the first submission and the latest acceptance (or publication) of each article, in a single reading of its history
        $latestStatuses = $latestStatus === Paper::STATUS_STRICTLY_ACCEPTED ? self::ACCEPTED_STATUSES : [$latestStatus];
        $firstSubmission = 'MIN(CASE WHEN history.status = :submitted THEN history.date ELSE NULL END)';
        $latestDecision = 'MAX(CASE WHEN history.status IN (:latestStatuses) THEN history.date ELSE NULL END)';

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                sprintf('YEAR(%s) AS year', $latestDecision),
                'history.rvid AS rvid',
                sprintf('TIMESTAMPDIFF(%s, %s, %s) AS delay', $unit, $firstSubmission, $latestDecision),
                'history.paperid AS PAPERID',
                sprintf('%s AS min_date', $firstSubmission),
                sprintf('%s AS max_date', $latestDecision)
            )
            ->from(PaperLog::class, 'history')
            ->andWhere('history.status IN (:statuses)')
            ->setParameter('statuses', array_merge([Paper::STATUS_SUBMITTED], $latestStatuses))
            ->setParameter('submitted', Paper::STATUS_SUBMITTED)
            ->setParameter('latestStatuses', $latestStatuses)
            ->groupBy('history.paperid', 'history.rvid')
            // a submission and an acceptance (or publication) in the history of the article
            ->having(sprintf('%s IS NOT NULL AND %s IS NOT NULL', $firstSubmission, $latestDecision));

        // an article is left out if one of its versions is imported (or submitted before the start date)
        $excluded = 'excluded.flag = :imported';

        if (self::isValidDate($startDate)) {
            $excluded .= ' OR excluded.submissionDate < :startDate';
            $qb->setParameter('startDate', $startDate);
        }

        $qb->andWhere(sprintf('NOT EXISTS (SELECT excluded.docid FROM %s excluded WHERE excluded.paperid = history.paperid AND (%s))', Paper::class, $excluded))
            ->setParameter('imported', 'imported');

        if ($rvId) {
            $qb->andWhere('history.rvid = :rvid')->setParameter('rvid', $rvId);
        } else {
            // the active journals, checked by article (in HAVING: the history is read from the index on the statuses)
            $qb->andHaving(sprintf('history.rvid IN (SELECT activeJournal.rvid FROM %s activeJournal WHERE activeJournal.status = :activeJournalStatus)', Review::class))
                ->setParameter('activeJournalStatus', Review::STATUS_ENABLED);
        }

        if ($validYears !== null) {
            $qb->andHaving(sprintf('YEAR(%s) IN (:years)', $latestDecision))->setParameter('years', $validYears);
        }

        try {
            return $qb->getQuery()->getScalarResult();
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::CRITICAL, $e->getMessage(), ['Exception' => $e]);
        }

        return null;
    }

    public function getNbPapersByStatusSql(bool $isSubmittedSameYear = true, $as = 'totalNumberOfPapersAccepted', int $status = 4, bool $withoutImported = true): string
    {
        $papers = 'PAPERS';
        $paperLog = 'PAPER_LOG';

        // the submission date of a paper is the one of its first version (the oldest of its versions)
        $year = $isSubmittedSameYear ? "pl.DATE" : "firstSubmission.SUBMISSION_DATE";

        $sql = "SELECT $papers.RVID AS rvid, YEAR($year) AS `year`, COUNT(DISTINCT($papers.PAPERID)) AS $as";
        $sql .= " FROM $paperLog pl JOIN $papers ON $papers.DOCID = pl.DOCID";
        $sql .= " JOIN (SELECT PAPERID, MIN(SUBMISSION_DATE) AS SUBMISSION_DATE FROM $papers GROUP BY PAPERID) firstSubmission";
        $sql .= " ON firstSubmission.PAPERID = $papers.PAPERID AND YEAR(firstSubmission.SUBMISSION_DATE) = YEAR(pl.DATE)";
        $sql .= " WHERE pl.status IS NOT NULL AND pl.status = $status";

        if ($withoutImported) {
            $sql .= " AND $papers.FLAG = 'submitted'";
        }

        $sql .= " GROUP BY rvid, `year`";
        $sql .= " ORDER BY rvid, `year` DESC";


        return $sql;

    }

    public function totalNbPapersByStatusStatement(bool $isSubmittedSameYear = true, $as = Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Paper::STATUS_STRICTLY_ACCEPTED): ?Statement
    {

        $conn = $this->getEntityManager()->getConnection();

        try {
            return $conn->prepare($this->getNbPapersByStatusSql($isSubmittedSameYear, $as, $status));

        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return null;


    }


    public function getSubmissionMedianTimeByStatusQuery(?int $rvId = null, ?array $years = null, ?string $startAfterDate = null, string $operationName = 'median-submission-publication', string $unit = 'week'): float|int|null
    {
        return $this->getSubmissionMedianTimeDetails($rvId, $years, $startAfterDate, $operationName, $unit)['median'];
    }

    /**
     * Median delay and number of articles it is computed from.
     *
     * @return array{median: float|int|null, count: int}
     */
    public function getSubmissionMedianTimeDetails(?int $rvId = null, ?array $years = null, ?string $startAfterDate = null, string $operationName = 'median-submission-publication', string $unit = 'week'): array
    {
        return $this->medianDetails(array_column($this->getSubmissionDelays($rvId, $years, $startAfterDate, $operationName, $unit), self::DELAY));
    }

    /**
     * Median delay and number of articles of each journal (one query for all the journals);
     * the journals without any article are not returned.
     *
     * @return array<int, array{median: float|int|null, count: int}> indexed by journal identifier (rvid)
     */
    public function getSubmissionMedianTimeDetailsByJournal(?array $years = null, ?string $startAfterDate = null, string $operationName = 'median-submission-publication', string $unit = 'week'): array
    {
        $delaysByJournal = [];

        foreach ($this->getSubmissionDelays(null, $years, $startAfterDate, $operationName, $unit) as $row) {
            $delaysByJournal[(int)$row['rvid']][] = $row[self::DELAY];
        }

        return array_map($this->medianDetails(...), $delaysByJournal);
    }

    /**
     * Delay of each article between its first submission and its latest publication (or acceptance), imported articles excluded.
     */
    private function getSubmissionDelays(?int $rvId, ?array $years, ?string $startAfterDate, string $operationName, string $unit): array
    {
        $status = $operationName !== 'median-submission-publication' ? Paper::STATUS_STRICTLY_ACCEPTED : Paper::STATUS_PUBLISHED;

        return $this->getArticleDelays($unit, $status, $startAfterDate, $years, $rvId) ?? [];
    }

    /**
     * @return array{median: float|int|null, count: int}
     */
    private function medianDetails(array $delays): array
    {
        $validValues = array_filter($delays, is_numeric(...));

        return ['median' => $this->getMedian($validValues), 'count' => count($validValues)];
    }

    private function commonQuery(?int $rvId = null, array $years = [], ?string $startAfterDate = null, int|array $status = [Paper::STATUS_STRICTLY_ACCEPTED], bool $ignoreImportedArticles = false): QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect("COUNT(DISTINCT(pl.paperid)) AS total");

        if ($years !== []) {
            $qb->addSelect("YEAR(pl.date) As year");
        }

        $qb->from(PaperLog::class, 'pl')
            ->innerJoin(Paper::class, 'p', Join::ON, 'pl.docid = p.docid')
            ->andWhere('p.status != :deleted')->setParameter('deleted', Paper::STATUS_DELETED)
            ->andWhere('p.status != :removed')->setParameter('removed', Paper::STATUS_REMOVED)
            ->andWhere("pl.status IS NOT NULL");


        if (is_array($status)) {
            $this->andOrExp($qb, 'pl.status', $status);

        } else {
            $qb->andWhere('pl.status = :status')->setParameter('status', $status);
        }


        if ($ignoreImportedArticles) {
            $qb->andWhere("p.flag = :flag");
            $qb->setParameter('flag', 'submitted');
        }

        if (self::isValidDate($startAfterDate)) {
            // first submission of the paper (the oldest submission date of its versions), as for the submissions
            $qb->andWhere(sprintf('p.paperid IN (SELECT firstSubmission.paperid FROM %s firstSubmission GROUP BY firstSubmission.paperid HAVING MIN(firstSubmission.submissionDate) > :date)', Paper::class))
                ->setParameter('date', $startAfterDate);
        }

        if ($rvId) {
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
        } else {
            $this->andActiveJournals($qb, 'p.rvid');
        }

        return $qb;

    }


    public function getAccepted(?int $rvId = null, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): int
    {
        return $this->processResult($this->acceptedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * @return array<int, int> number of accepted papers indexed by year (one query for all the years)
     */
    public function getAcceptedByYear(?int $rvId, array $years, ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByYear($this->acceptedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    public function getRefused(?int $rvId = null, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): int
    {
        return $this->processResult($this->refusedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * Same count as getAccepted(), for each journal (one query for all the journals)
     *
     * @return array<int, int> indexed by journal identifier (rvid); the journals without accepted paper are not returned
     */
    public function getAcceptedByJournal(array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByJournal($this->acceptedQuery(null, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * Same count as getRefused(), for each journal (one query for all the journals)
     *
     * @return array<int, int> indexed by journal identifier (rvid); the journals without refused paper are not returned
     */
    public function getRefusedByJournal(array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByJournal($this->refusedQuery(null, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * @return array<int, int> number of refused papers indexed by year (one query for all the years)
     */
    public function getRefusedByYear(?int $rvId, array $years, ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByYear($this->refusedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    private function acceptedQuery(?int $rvId, array $years, ?string $startAfterDate, bool $ignoreImportedArticles): QueryBuilder
    {
        return $this->commonQuery($rvId, $years, $startAfterDate, self::ACCEPTED_STATUSES, $ignoreImportedArticles);
    }

    private function refusedQuery(?int $rvId, array $years, ?string $startAfterDate, bool $ignoreImportedArticles): QueryBuilder
    {
        return $this->commonQuery($rvId, $years, $startAfterDate, self::REFUSED_STATUS, $ignoreImportedArticles);
    }

    private function publishedQuery(?int $rvId, array $years, ?string $startAfterDate, bool $ignoreImportedArticles): QueryBuilder
    {
        return $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_PUBLISHED, $ignoreImportedArticles);
    }

    private function processResult(QueryBuilder $qb, array $years = []): int
    {
        // each paper is counted once over all the years, as in getPaperIds(): a paper decided in several years is counted in each of them by processResultByYear()
        if ($years !== []) {
            $qb->select('COUNT(DISTINCT(pl.paperid)) AS total');
            $this->andOrExp($qb, 'YEAR(pl.date)', $years);
        }

        try {
            return (int)$qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException  $e) {
            $this->logger->critical($e->getMessage());
            return 0;
        }
    }

    /**
     * Identifiers (PAPERID) of the papers counted by getAccepted(), getRefused(), getPublished() and getAllAcceptedNotYetPublished()
     *
     * @param string $figure one of the PAPER_IDS_FIGURES
     * @return list<int> sorted; with several years, a paper decided in several of them is listed once
     */
    public function getPaperIds(string $figure, int $rvId, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        $qb = match ($figure) {
            self::PAPER_IDS_FIGURES['accepted'] => $this->acceptedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles),
            self::PAPER_IDS_FIGURES['refused'] => $this->refusedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles),
            self::PAPER_IDS_FIGURES['published'] => $this->publishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles),
            self::PAPER_IDS_FIGURES['accepted-not-yet-published'] => $this->acceptedNotYetPublishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles),
            default => throw new \InvalidArgumentException(sprintf('Unknown figure "%s"', $figure)),
        };

        $qb->select('DISTINCT pl.paperid AS paperid')->orderBy('paperid');

        if ($years !== []) {
            $this->andOrExp($qb, 'YEAR(pl.date)', $years);
        }

        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'paperid'));
    }

    /**
     * Identifiers (PAPERID) of the papers a median delay is computed from (see getSubmissionMedianTimeDetails())
     *
     * @return list<int> sorted
     */
    public function getSubmissionDelayPaperIds(int $rvId, ?array $years = null, ?string $startAfterDate = null, string $operationName = 'median-submission-publication', string $unit = 'week'): array
    {
        $ids = [];

        foreach ($this->getSubmissionDelays($rvId, $years, $startAfterDate, $operationName, $unit) as $row) {
            if (is_numeric($row[self::DELAY])) {
                $ids[] = (int)$row['PAPERID'];
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Totals of each journal, computed as processResult(): with years, each paper is counted once over all the years.
     *
     * @return array<int, int> indexed by journal identifier (rvid)
     */
    private function processResultByJournal(QueryBuilder $qb, array $years): array
    {
        $qb->select('COUNT(DISTINCT(pl.paperid)) AS total, p.rvid AS rvid')->groupBy('rvid');

        if ($years !== []) {
            $this->andOrExp($qb, 'YEAR(pl.date)', $years);
        }

        $totals = [];

        foreach ($qb->getQuery()->getResult() as $values) {
            $totals[(int)$values['rvid']] = (int)$values['total'];
        }

        return $totals;
    }

    /**
     * @return array<int, int> totals indexed by year; the requested years without result are set to 0
     */
    private function processResultByYear(QueryBuilder $qb, array $years): array
    {
        $totals = array_fill_keys(array_map('intval', $years), 0);

        if ($years === []) {
            return $totals;
        }

        $this->andOrExp($qb, 'YEAR(pl.date)', $years);
        $qb->addGroupBy('year');

        foreach ($qb->getQuery()->getResult() as $values) {
            $totals[(int)$values['year']] = (int)$values['total'];
        }

        return $totals;
    }

    public function getSubmissions(?int $rvId = null, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): int
    {
        $qb = $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_SUBMITTED, $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }

    public function getPublished(?int $rvId = null, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): int
    {
        return $this->processResult($this->publishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * @return array<int, int> number of published papers indexed by year (one query for all the years)
     */
    public function getPublishedByYear(?int $rvId, array $years, ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByYear($this->publishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }


    public function getAllAcceptedNotYetPublished(?int $rvId = null, array $years = [], ?string $startAfterDate = null, bool $ignoreImportedArticles = true): int
    {
        return $this->processResult($this->acceptedNotYetPublishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * @return array<int, int> number of accepted but not yet published papers indexed by year (one query for all the years)
     */
    public function getAllAcceptedNotYetPublishedByYear(?int $rvId, array $years, ?string $startAfterDate = null, bool $ignoreImportedArticles = true): array
    {
        return $this->processResultByYear($this->acceptedNotYetPublishedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles), $years);
    }

    /**
     * Accepted papers that have been neither published nor refused (an abandoned paper is only paused: it is still counted).
     * One NOT EXISTS per status: with an OR on the status, MySQL materializes the subquery with a full scan of PAPER_LOG.
     */
    private function acceptedNotYetPublishedQuery(?int $rvId, array $years, ?string $startAfterDate, bool $ignoreImportedArticles): QueryBuilder
    {
        $qb = $this->acceptedQuery($rvId, $years, $startAfterDate, $ignoreImportedArticles);

        $excludedStatuses = [
            'pStatus' => Paper::STATUS_PUBLISHED, // accepted and published
            'rStatus' => Paper::STATUS_REFUSED, // accepted and refused
        ];

        foreach ($excludedStatuses as $parameter => $status) {
            $alias = sprintf('pl_%s', $parameter);

            $subQb = $this->getEntityManager()->createQueryBuilder()
                ->addSelect('1')
                ->from(PaperLog::class, $alias)
                ->andWhere(sprintf('%s.paperid = pl.paperid', $alias))
                ->andWhere(sprintf('%s.status = :%s', $alias, $parameter));

            $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQb->getDQL())))
                ->setParameter($parameter, $status);
        }

        return $qb;
    }


    private function getRateByStatus(string $status = 'accepted', array $options = []): float|null
    {
        $years = $options['year'] ?? [];
        $rvId = isset($options['rvid']) ? (int)$options['rvid'] : null;
        $startAfterDate = $options['startAfterDate'] ?? null;

        $acceptedTotal = $options['acceptedTotal'] ?? $this->getAccepted($rvId, $years, $startAfterDate);
        $refusedTotal = $options['refusedTotal'] ?? $this->getRefused($rvId, $years, $startAfterDate);

        $totalBase = $acceptedTotal + $refusedTotal; // Formule choisie lors de la réunion Epi. du 14/01/2026 (correspond à 100%)

        if ($totalBase === 0) {
            return null;
        }

        $current = match ($status) {
            'accepted' => $acceptedTotal,
            'refused' => $refusedTotal,
            'published' => $options['publishedTotal'] ?? $this->getPublished($rvId, $years, $startAfterDate),
            default => null,
        };

        if ($current === null) {
            return null;
        }

        return round(($current / $totalBase) * 100, AppConstants::RATE_DEFAULT_PRECISION, \RoundingMode::HalfAwayFromZero);
    }

    public function getPublicationRate(array $options = []): float|null
    {
        return $this->getRateByStatus('published', $options);
    }

    public function getAcceptanceRate(array $options = []): float|null
    {
        return $this->getRateByStatus('accepted', $options);

    }

    public function getRefusalRate(array $options = []): float|null
    {
        return $this->getRateByStatus('refused', $options);
    }
}
