<?php
declare(strict_types=1);

namespace App\Repository\Main;

use App\Entity\Main\PaperLog;
use App\Entity\Main\Papers;
use App\Resource\StatResource;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    public const AVAILABLE_FILTERS = ['rvid', 'submissionDate', 'method', 'unit', 'withDetails'];

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
     * @return StatResource
     */
    public function getDelayBetweenSubmissionAndLatestStatus(array $filters = [], int $latestStatus = Papers::STATUS_ACCEPTED): StatResource
    {

        $year = null;
        $rvId = null;
        $method = 'average';
        $unit = 'DAY';

        $withDetails = array_key_exists('withDetails', $filters['is']);
        $filters['is']['withDetails'] = $withDetails;

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

        $statResource = new StatResource();
        $statResource->setAvailableFilters(self::AVAILABLE_FILTERS);
        $statResource->setRequestedFilters($filters['is']);
        $statResourceName = $method . ucwords(strtolower($unit)) . 'sSubmission';
        $statResourceName .= $latestStatus === Papers::STATUS_PUBLISHED ? 'Publication' : 'Acceptation';
        $statResource->setName($statResourceName);


        $rawSql = "
             SELECT year, RVID AS rvid, ROUND(AVG(delay), 0) AS delay
             FROM ( 
                 SELECT YEAR(SUBMISSION_FROM_LOGS.DATE) AS year, SUBMISSION_FROM_LOGS.RVID, ABS(TIMESTAMPDIFF($unit, SUBMISSION_FROM_LOGS.DATE, JOINED_TABLE_ALIAS.DATE)) AS delay
                 FROM ( 
                      SELECT * 
                      FROM PAPER_LOG WHERE ACTION LIKE 'status' AND (DETAIL LIKE '{\"status\":" . Papers::STATUS_SUBMITTED . "}' OR DETAIL LIKE '{\"status\":\"" . Papers::STATUS_SUBMITTED . "\"}' ) GROUP BY PAPERID 
                      ) AS SUBMISSION_FROM_LOGS INNER JOIN (   
                                                SELECT *
                                                FROM PAPER_LOG 
                                                WHERE ACTION LIKE 'status' AND (DETAIL LIKE '{\"status\":\"$latestStatus\"}' OR DETAIL LIKE '{\"status\":$latestStatus}') 
                                                GROUP BY PAPERID ) AS JOINED_TABLE_ALIAS USING (PAPERID) 
                 GROUP BY PAPERID ) AS DELAY_SUBMISSION_LATEST_STATUS
             GROUP BY rvid, year 
             ORDER BY year ASC, rvid ASC  ";

        $conn = $this->getEntityManager()->getConnection();

        try {
            $stmt = $conn->prepare($rawSql);

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
                    $statResource->setDetails($this->reformatData($rvIdResult));
                }

                return $statResource;
            }

            if ($year && $rvId) {
                $details = $this->applyFilterBy($result, 'rvid', $rvId);

                if ($withDetails) {
                    $statResource->setDetails($details);
                }

                if (array_key_exists($rvId, $details)) {
                    foreach ($details [$rvId] as $key => $value) {
                        if ((int)$value['year'] === (int)$year) {
                            $yearResult[$rvId] = $value;
                            $statResource->setValue((float)$yearResult[$rvId]['delay']);
                            return $statResource;
                        }
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

        } catch (Exception | \Doctrine\DBAL\Driver\Exception $e) {
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

    private function reformatData(array $array): array
    {
        $year = null;
        $result = [];

        foreach ($array as $rvId => $value) {

            foreach ($value as $v) {

                foreach ($v as $kv => $vv) {
                    if ($kv === 'year') {
                        $year = $vv;
                    }

                    if ($kv === 'delay') {

                        $result[$rvId][$year][$kv] = $vv;

                    }
                }

            }
        }

        return $result;
    }
}
