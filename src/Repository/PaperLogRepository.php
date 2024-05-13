<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\PaperLog;
use App\Entity\Paper;
use App\Service\Stats;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Statement;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @method PaperLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaperLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaperLog[]    findAll()
 * @method PaperLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */
class PaperLogRepository extends ServiceEntityRepository
{
    public const DELAY = 'delay';
    public const DEFAULT_UNIT = 'DAY';
    use ToolsTrait;

    public const AVAILABLE_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE];

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, PaperLog::class);
        $this->logger = $logger;
    }

    /**
     * Average number of days (or months) between submission and acceptance (or publication), by year and by journal
     * @param string $unit
     * @param int $latestStatus
     * @param string|null $startDate
     * @param string|null $year
     * @return array|null
     */

    public function delayBetweenSubmissionAndLatestStatus(string $unit = self::DEFAULT_UNIT, int $latestStatus = Paper::STATUS_ACCEPTED, string $startDate = null, string $year = null): ?array
    {
        $result = null;
        try {
            $conn = $this->getEntityManager()->getConnection();
            $stmt = $conn->prepare($this->query($unit, $latestStatus, $startDate, $year));
            $result = $stmt->executeQuery()->fetchAllAssociative();

        } catch (\Exception $e) {
            $this->logger->log(LogLevel::CRITICAL, $e->getMessage(), ['Exception' => $e]);
        }

        return $result;

    }

    private function query(string $unit = self::DEFAULT_UNIT, int $latestStatus = Paper::STATUS_ACCEPTED, string $startStatsDate = null, $year = null): string
    {


        $sql = "SELECT year, RVID AS rvid, ROUND(AVG(delay), 0) AS delay FROM (SELECT YEAR(SUBMISSION_FROM_LOGS.DATE) AS year, SUBMISSION_FROM_LOGS.RVID, ABS(TIMESTAMPDIFF($unit, SUBMISSION_FROM_LOGS.DATE, JOINED_TABLE_ALIAS.DATE)) AS delay FROM (SELECT * FROM PAPER_LOG WHERE ACTION LIKE 'status' ";

        if ($startStatsDate) {
            $sql .= "AND PAPER_LOG.DATE >= '$startStatsDate'";
        }

        $sql .= "AND (DETAIL LIKE '{\"status\":" . Paper::STATUS_SUBMITTED . "}' OR DETAIL LIKE '{\"status\":\"" . Paper::STATUS_SUBMITTED . "\"}' ) GROUP BY PAPERID) AS SUBMISSION_FROM_LOGS INNER JOIN (SELECT * FROM PAPER_LOG WHERE ACTION LIKE 'status' ";

        if ($startStatsDate) {
            $sql .= "AND PAPER_LOG.DATE >= '$startStatsDate'";
        }

        $sql .= " AND (DETAIL LIKE '{\"status\":\"$latestStatus\"}' OR DETAIL LIKE '{\"status\":$latestStatus}') GROUP BY PAPERID ) AS JOINED_TABLE_ALIAS USING (PAPERID) GROUP BY PAPERID ) AS DELAY_SUBMISSION_LATEST_STATUS";

        if ($year) {
            $sql .= " WHERE `year` = '$year'";
        }

        $sql .= " GROUP BY rvid, year ORDER BY year ASC, rvid ASC  ";

        return $sql;

    }

    public function getTotalNumberOfPapersByStatusSql(bool $isSubmittedSameYear = true, $as = 'totalNumberOfPapersAccepted', int $status = 4): string
    {
        $papers = 'PAPERS';
        $paperLog = 'PAPER_LOG';

        $year = $isSubmittedSameYear ? "$papers.SUBMISSION_DATE" : "$paperLog.DATE";


        return "SELECT $papers.RVID AS rvid, YEAR($year) AS `year`, COUNT(DISTINCT($papers.PAPERID)) AS $as FROM $paperLog JOIN $papers ON $papers.DOCID = $paperLog.DOCID
                WHERE ACTION LIKE 'status' AND(DETAIL LIKE '{\"status\":$status}' OR DETAIL LIKE '{\"status\":\"$status\"}') AND FLAG = 'submitted'
                GROUP BY rvid, `year`
                ORDER BY rvid, `year` DESC
                ";

    }

    public function totalNumberOfPapersByStatus(bool $isSubmittedSameYear = true, $as = Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Paper::STATUS_ACCEPTED): ?Statement
    {

        $conn = $this->getEntityManager()->getConnection();

        try {
            return $conn->prepare($this->getTotalNumberOfPapersByStatusSql($isSubmittedSameYear, $as, $status));

        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return null;


    }

}
