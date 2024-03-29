<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\DataProvider\ReviewStatsDataProvider;
use App\Entity\PaperLog;
use App\Entity\Papers;
use App\Resource\AbstractStatResource;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method PaperLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaperLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaperLog[]    findAll()
 * @method PaperLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */

class PaperLogRepository extends ServiceEntityRepository
{
    use ToolsTrait;

    public const AVAILABLE_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE];

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, PaperLog::class);
        $this->logger = $logger;
    }

    /**
     * Par annee, par revue, delai moyen, ou la valeur médiane en nombre de jours (ou mois) entre dépôt et acceptation
     * @param array $filters
     * @param int $latestStatus
     * @return ToBeDeletedStatResource
     */

    public function getDelayBetweenSubmissionAndLatestStatus(array $filters = [], int $latestStatus = Papers::STATUS_ACCEPTED): AbstractStatResource
    {

        $year = null;
        $rvId = null;
        $method = 'average';
        $unit = 'DAY';
        $startDate = null;

        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);
        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        if (array_key_exists('submissionDate', $filters['is']) && !empty($filters['is']['submissionDate'])) {
            $year = $filters['is']['submissionDate'];
        }

        if (array_key_exists('rvid', $filters['is']) && !empty($filters['is']['rvid'])) {
            $rvId = $filters['is']['rvid'];
        }

        if (array_key_exists('method', $filters['is']) && in_array($filters['is']['method'], ['average', 'median'], true)) {
            $method = $filters['is']['method'];
        }

        if (array_key_exists('unit', $filters['is']) && in_array($filters['is']['unit'], ['day', 'month'], true)) {
            $unit = strtoupper($filters['is']['unit']);
        }

        if (isset($filters['is'][AppConstants::START_AFTER_DATE])) {
            $startDate = $filters['is'][AppConstants::START_AFTER_DATE];
        }


        $statResource = $latestStatus ? new SubmissionPublicationDelayOutput() : new SubmissionAcceptanceDelayOutput();
        $statResource->setDetails([]);
        $statResource->setAvailableFilters(ReviewStatsDataProvider::AVAILABLE_FILTERS);
        $statResource->setRequestedFilters($filters['is']);
        $statResourceName = $method . ucwords(strtolower($unit)) . 'sSubmission';
        $statResourceName .= $latestStatus === Papers::STATUS_PUBLISHED ? 'Publication' : 'Acceptation';
        $statResource->setName($statResourceName);

        $conn = $this->getEntityManager()->getConnection();

        try {
            $stmt = $conn->prepare($this->getRawSql($unit, $latestStatus, $startDate, $year));

            $result = $stmt->executeQuery()->fetchAllAssociative();


            if ($year && !$rvId) { // all platform by year
                $yearResult = $this->applyFilterBy($result, 'year', $year);
                if (array_key_exists($year, $yearResult)) {
                    $statResource->setValue($this->avg($yearResult[$year]));
                } else {
                    $statResource->setValue(null);
                }

                if ($withDetails) {
                    $statResource->setDetails($result);
                }

                return $statResource;
            }

            if (!$year && $rvId) {
                $rvIdResult = $this->applyFilterBy($result, 'rvid', $rvId);
                if (array_key_exists($rvId, $rvIdResult)) {
                    $statResource->setValue($this->avg($rvIdResult[$rvId]));
                }

                if ($withDetails) {
                    $reformattedResult = $this->reformatData($rvIdResult);

                    if (isset($reformattedResult[$rvId])){
                        $statResource->setDetails($this->reformatData($rvIdResult)[$rvId]);
                    }

                }

                return $statResource;
            }

            if ($year && $rvId) {
                $details = $this->applyFilterBy($result, 'rvid', $rvId);

                if ($withDetails) {
                    $reformattedResult = $this->reformatData($details);
                    if (isset($reformattedResult[$rvId])) {
                        $statResource->setDetails($reformattedResult[$rvId]);
                        $statResource->setValue((float)$reformattedResult[$rvId][$year]['delay']);

                    }

                }

                $statResource->setValue(null);
                return $statResource;
            }

            // all platform stats (!year && !rvId)
            $statResource->setValue($this->avg($result));

            if ($withDetails) {
                $statResource->setDetails($result);
            }

            return $statResource;

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $statResource;

    }

    /**
     * @param array $array
     * @param string $key
     * @return float | null
     */
    private function avg(array $array, string $key = 'delay'): ?float
    {

        if (empty($array)) {
            return null;
        }
        $avg = 0;
        foreach ($array as $detail) {
            $avg += (int)$detail[$key];
        }
        return round($avg / count($array), 0);
    }

    private function reformatData(array $array, string $extractedField = 'delay'): array
    {
        $year = null;
        $result = [];

        foreach ($array as $rvId => $value) {

            foreach ($value as $v) {

                foreach ($v as $kv => $vv) {

                    if ($kv === 'year') {
                        $year = $vv;
                    }

                    if ($kv === $extractedField) {
                        $result[$rvId][$year][$kv] = $vv;
                    }
                }

            }
        }

        return $result;
    }

    private function getRawSql(string $unit, int $latestStatus, string $startStatsDate = null, $year= null): string
    {


        $sql = "SELECT year, RVID AS rvid, ROUND(AVG(delay), 0) AS delay FROM (SELECT YEAR(SUBMISSION_FROM_LOGS.DATE) AS year, SUBMISSION_FROM_LOGS.RVID, ABS(TIMESTAMPDIFF($unit, SUBMISSION_FROM_LOGS.DATE, JOINED_TABLE_ALIAS.DATE)) AS delay FROM (SELECT * FROM PAPER_LOG WHERE ACTION LIKE 'status' ";

        if($startStatsDate) {
            $sql .= "AND PAPER_LOG.DATE >= '$startStatsDate'";
        }

        $sql .=  "AND (DETAIL LIKE '{\"status\":" . Papers::STATUS_SUBMITTED . "}' OR DETAIL LIKE '{\"status\":\"" . Papers::STATUS_SUBMITTED . "\"}' ) GROUP BY PAPERID) AS SUBMISSION_FROM_LOGS INNER JOIN (SELECT * FROM PAPER_LOG WHERE ACTION LIKE 'status' ";

        if ($startStatsDate){
            $sql .=  "AND PAPER_LOG.DATE >= '$startStatsDate'";
        }

        $sql .= " AND (DETAIL LIKE '{\"status\":\"$latestStatus\"}' OR DETAIL LIKE '{\"status\":$latestStatus}') GROUP BY PAPERID ) AS JOINED_TABLE_ALIAS USING (PAPERID) GROUP BY PAPERID ) AS DELAY_SUBMISSION_LATEST_STATUS";

        if($year) {
            $sql .= " WHERE `year` = '$year'";
        }

        $sql .= " GROUP BY rvid, year ORDER BY year ASC, rvid ASC  ";

        return $sql;

    }

    public function getTotalNumberOfPapersByStatus($rvId = null, bool $isSubmittedSameYear = true, $as = PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = 4): array
    {

        $conn = $this->getEntityManager()->getConnection();

        try {
            $stmt = $conn->prepare($this->getTotalNumberOfPapersByStatusSql($isSubmittedSameYear, $as, $status));

            if ($rvId) {

                //before reformat data : [ 8 => [0 => ["year" => 2023, PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [], ... ]
                return $this->reformatData(
                    $this->applyFilterBy($stmt->executeQuery()->fetchAllAssociative(), 'rvid', $rvId),
                    $as
                );

                //after: [8 => [ 2023 => [PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [2022 => ], .....


            }

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return [];



    }



    private function getTotalNumberOfPapersByStatusSql(bool $isSubmittedSameYear = true, $as = 'totalNumberOfPapersAccepted', int $status = 4): string
    {
        $papers = 'PAPERS';
        $paperLog = 'PAPER_LOG';

        $year = $isSubmittedSameYear  ? "$papers.SUBMISSION_DATE" : "$paperLog.DATE";


        return "SELECT $papers.RVID AS rvid, YEAR($year) AS `year`, COUNT(DISTINCT($papers.PAPERID)) AS $as FROM $paperLog JOIN $papers ON $papers.DOCID = $paperLog.DOCID
                WHERE ACTION LIKE 'status' AND(DETAIL LIKE '{\"status\":$status}' OR DETAIL LIKE '{\"status\":\"$status\"}') AND FLAG = 'submitted'
                GROUP BY rvid, `year`
                ORDER BY rvid, `year` DESC
                ";

    }

}
