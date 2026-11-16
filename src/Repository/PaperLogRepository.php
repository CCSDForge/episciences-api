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
    /** Statuses of an accepted article in its history (see PapersRepository::getSubmissions() and getArticleDelays()) */
    public const array ACCEPTED_STATUSES = [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED];
    /** Status of a refused article in its history (see PapersRepository::getSubmissions()) */
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

    private function getRateByStatus(string $status = 'accepted', array $options = []): float|null
    {
        // counted by submission by the callers (see SubmissionFigures)
        if (!isset($options['acceptedTotal'], $options['refusedTotal']) || ($status === 'published' && !isset($options['publishedTotal']))) {
            throw new \InvalidArgumentException('The totals of the rate are required to compute it.');
        }

        $acceptedTotal = (int)$options['acceptedTotal'];
        $refusedTotal = (int)$options['refusedTotal'];

        $totalBase = $acceptedTotal + $refusedTotal; // Formule choisie lors de la réunion Epi. du 14/01/2026 (correspond à 100%)

        if ($totalBase === 0) {
            return null;
        }

        $current = match ($status) {
            'accepted' => $acceptedTotal,
            'refused' => $refusedTotal,
            'published' => (int)$options['publishedTotal'],
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
