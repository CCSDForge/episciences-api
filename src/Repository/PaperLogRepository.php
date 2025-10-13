<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\PaperLog;
use App\Entity\Paper;
use App\Service\Stats;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
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
    use QueryTrait;
    use ToolsTrait;

    public const DELAY = 'delay';
    public const DEFAULT_UNIT = 'DAY';

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
     * @param string|int|array|null $years
     * @param string $method
     * @param int|null $rvId
     * @return array|null
     */

    public function delayBetweenSubmissionAndLatestStatus(string           $unit = self::DEFAULT_UNIT,
                                                          int              $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED,
                                                          string           $startDate = null,
                                                          string|int|array $years = null,
                                                          string           $method = Stats::DEFAULT_METHOD,
                                                          int              $rvId = null
    ): ?array
    {
        $result = null;
        try {
            $conn = $this->getEntityManager()->getConnection();
            $stmt = $conn->prepare($this->query($unit, $latestStatus, $startDate, $years, $rvId, $method));
            $result = $stmt->executeQuery()->fetchAllAssociative();

        } catch (\Exception $e) {
            $this->logger->log(LogLevel::CRITICAL, $e->getMessage(), ['Exception' => $e]);
        }


        return $result;

    }

    private function query(
        string           $unit = self::DEFAULT_UNIT,
        int              $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED,
        string           $startStatsDate = null,
        string|int|array $years = null,
        int              $rvId = null,
        string           $method = Stats::DEFAULT_METHOD,
    ): string
    {

        if ($method === Stats::MEDIAN_METHOD) {
            return $this->timeDiffByArticleQuery($unit, $latestStatus, $startStatsDate, $rvId, $years);
        }

        $sql = "SELECT year, rvid, ROUND(AVG(t3.delay), 0) AS delay FROM (";

        $sql .= $this->timeDiffByArticleQuery($unit, $latestStatus, $startStatsDate, $rvId, $years);

        $sql .= ") t3 GROUP BY rvid, year";

        if ($years || $rvId) {

            $sql .= ' HAVING';


            if ($rvId) {
                $sql .= " rvid = $rvId";
            }

            if ($years) {

                $sql .= !$rvId . '' . ' AND';

                if (is_array($years)) {
                    $years = implode(',', $years);
                    $sql .= " year IN ($years)";
                } else {
                    $sql .= " year = $years";
                }

            }
        }

        return $sql;

    }


    private function timeDiffByArticleQuery(string           $unit = self::DEFAULT_UNIT,
                                            int              $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED,
                                            string           $startStatsDate = null,
                                            int              $rvId = null,
                                            array|string|int $years = null,

    ): string
    {

        $referenceStatus = Paper::STATUS_SUBMITTED;

        $sql = "SELECT YEAR(t2.max_date) AS year, t1.RVID AS rvid, TIMESTAMPDIFF($unit, min_date, max_date) AS delay, t1.PAPERID, t1.min_date, t2.max_date FROM (";
        $sql .= "SELECT pl1.PAPERID, MIN(pl1.DATE) AS min_date, pl1.RVID FROM PAPER_LOG pl1 WHERE pl1.status IS NOT NULL AND pl1.status = $referenceStatus";

        if ($startStatsDate) {
            $sql .= " AND DATE(pl1.DATE) > '$startStatsDate'";
        }


        $sql .= " GROUP BY pl1.PAPERID, pl1.RVID ) t1 INNER JOIN(";
        $sql .= " SELECT pl2.PAPERID, MAX(pl2.DATE) AS max_date, pl2.RVID FROM PAPER_LOG pl2 WHERE pl2.status IS NOT NULL AND pl2.status = ";
        $sql .= ($latestStatus === Paper::STATUS_STRICTLY_ACCEPTED ? Paper::STATUS_STRICTLY_ACCEPTED . " OR pl2.status = " . Paper::STATUS_TMP_VERSION_ACCEPTED : $latestStatus);
        $sql .= " GROUP BY pl2.PAPERID, pl2.RVID ) t2 ON t1.PAPERID = t2.PAPERID AND t1.RVID = t2.RVID HAVING t1.PAPERID NOT IN(";
        $sql .= " SELECT DISTINCT p.PAPERID FROM PAPERS p WHERE p.FLAG = 'imported')";

        if ($rvId) {
            $sql .= " AND rvid = $rvId";
        }

        if ($years) {

            $sql .= ' AND';

            if (is_array($years)) {
                $years = implode(',', $years);
                $sql .= " YEAR(t2.max_date) IN ($years)";
            } else {
                $sql .= " YEAR(t2.max_date) = $years";
            }

        }

        return $sql;
    }


    public function getNbPapersByStatusSql(bool $isSubmittedSameYear = true, $as = 'totalNumberOfPapersAccepted', int $status = 4, bool $withoutImported = true): string
    {
        $papers = 'PAPERS';
        $paperLog = 'PAPER_LOG';

        $year = !$isSubmittedSameYear ? "$papers.SUBMISSION_DATE" : "pl.DATE";


        $sql = "SELECT $papers.RVID AS rvid, YEAR($year) AS `year`, COUNT(DISTINCT($papers.PAPERID)) AS $as";
        $sql .= " FROM $paperLog pl JOIN $papers ON $papers.DOCID = pl.DOCID AND YEAR($papers.SUBMISSION_DATE) = YEAR(pl.DATE)";
        $sql .= " WHERE pl.status IS NOT NULL AND pl.status = $status";

        if ($withoutImported) {
            $sql .= " AND $papers.FLAG = 'submitted'";
        }

        $sql .= "GROUP BY rvid, `year`";
        $sql .= "ORDER BY rvid, `year` DESC";


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


    public function getSubmissionMedianTimeByStatusQuery(int $rvId = null, array $years = null, string $startAfterDate = null, string $operationName = 'median-submission-publication', string $unit = 'week'): float|int|null
    {
        $unit = strtoupper($unit);

        if (!in_array($unit, ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'])) {
            $unit = 'WEEK';
        }

        $status = $operationName !== 'median-submission-publication' ? Paper::STATUS_STRICTLY_ACCEPTED : Paper::STATUS_PUBLISHED;

        $result = $this->delayBetweenSubmissionAndLatestStatus($unit, $status, $startAfterDate, $years, 'median', $rvId) ?? [];

        $delay = array_column($result, self::DELAY);

        $validValues = array_filter($delay, static function ($value) {
            return is_numeric($value);
        });

        try {
            $median = $this->getMedian($validValues);

        } catch (\LengthException $e) {
            $this->logger->critical($e->getMessage());
            $median = null;
        }

        return $median;

    }

    private function commonQuery(int $rvId = null, array $years = [], string $startAfterDate = null, int|array $status = [Paper::STATUS_STRICTLY_ACCEPTED], bool $ignoreImportedArticles = false): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect("COUNT(DISTINCT(pl.paperid)) AS total");

        if (!empty($years)) {
            $qb->addSelect("YEAR(pl.date) As year");
        }

        $qb->from(PaperLog::class, 'pl')
            ->innerJoin(Paper::class, 'p', Join::WITH, 'pl.docid = p.docid')
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
            $qb->andWhere('p.submissionDate > :date')->setParameter('date', $startAfterDate);
        }

        if ($rvId) {
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
        }
        $sql = $qb->getQuery()->getSQL();

        return $qb;

    }


    public function getAccepted(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true): float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED], $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }

    public function getRefused(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true): float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_REFUSED, $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }

    private function processResult(\Doctrine\ORM\QueryBuilder $qb, array $years = []): float
    {
        $total = 0;

        if (empty($years)) {
            try {
                return $qb->getQuery()->getSingleScalarResult();
            } catch (NoResultException|NonUniqueResultException  $e) {
                $this->logger->critical($e->getMessage());
            }
        } else {


            $this->andOrExp($qb, 'YEAR(pl.date)', $years);
            $qb->addGroupBy('year');

            $result = $qb->getQuery()->getResult();

            foreach ($result as $values) {
                $total += $values['total'];
            }

        }

        return $total;

    }

    public function getPublished(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true): float
    {
        $qb = $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_PUBLISHED, $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }


    public function getAllAcceptedNotYetPublished(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true): float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED], $ignoreImportedArticles);

        $subQb = $this->getEntityManager()->createQueryBuilder();
        $subQb1 = $this->getEntityManager()->createQueryBuilder();


        // accepted and published
        $subQb->addSelect('1')
            ->from(PaperLog::class, 'pl1')
            ->andWhere('pl1.paperid = pl.paperid')
            ->andWhere('pl1.status IS NOT NULL')
            ->andWhere('pl1.status = :pStatus');

        // accepted and (refused or abandoned)

        $subQb1->addSelect('1')
            ->from(PaperLog::class, 'pl2')
            ->andWhere('pl2.paperid = pl.paperid')
            ->andWhere('pl2.status IS NOT NULL')
            ->andWhere('pl2.status = :rStatus OR pl2.status = :aStatus');


        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQb->getDQL())));
        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQb1->getDQL())));
            $qb->setParameter('pStatus', Paper::STATUS_PUBLISHED)
                ->setParameter('rStatus', Paper::STATUS_REFUSED)
                ->setParameter('aStatus', Paper::STATUS_ABANDONED);

        return $this->processResult($qb, $years);

    }

}
