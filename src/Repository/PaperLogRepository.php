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
     * @param string|null $year
     * @return array|null
     */

    public function delayBetweenSubmissionAndLatestStatus(string $unit = self::DEFAULT_UNIT, int $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED, string $startDate = null, string $year = null): ?array
    {
        $result = null;
        try {
            $conn = $this->getEntityManager()->getConnection();
            $stmt = $conn->prepare($this->query($unit, $latestStatus, $startDate, $year));
            $result = $stmt->executeQuery()->fetchAllAssociative();

        } catch (\Exception $e) {
            $this->logger->log(LogLevel::CRITICAL, $e->getMessage(), ['Exception' => $e]);
        }

        //$this->logger->debug('Executed SQL query: ' . $this->query($unit, $latestStatus, $startDate, $year));
        //$this->logger->debug('Result obtained:', ['result' => $result]);

        return $result;

    }

    private function query(string $unit = self::DEFAULT_UNIT, int $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED, string $startStatsDate = null, $year = null): string
    {

        $initialStatus = Paper::STATUS_SUBMITTED;
        $sql = "SELECT YEAR(max_date) AS year, rvid, ROUND(AVG(delay_by_article), 0) AS delay FROM (";
        $sql .= " SELECT t1.PAPERID, t1.min_date, t2.max_date, t1.RVID AS rvid,  TIMESTAMPDIFF($unit, min_date, max_date) AS delay_by_article FROM (";
        $sql .= " SELECT PAPERID, MIN(DATE) AS min_date, RVID FROM PAPER_LOG WHERE `status` IS NOT NULL AND `status` = $initialStatus";

        if ($startStatsDate) {
            $sql .= " AND DATE(DATE) > '$startStatsDate'";
        }

        $sql .= " GROUP BY PAPERID, RVID ) t1 INNER JOIN(";
        $sql .= " SELECT PAPERID, MAX(DATE) AS max_date, RVID FROM PAPER_LOG WHERE `status`IS NOT NULL AND `status` = " . ($latestStatus === Paper::STATUS_STRICTLY_ACCEPTED ? Paper::STATUS_STRICTLY_ACCEPTED . " OR status = " . Paper::STATUS_TMP_VERSION_ACCEPTED : $latestStatus);
        $sql .= " GROUP BY PAPERID, RVID ) t2 ON t1.PAPERID = t2.PAPERID AND t1.RVID = t2.RVID HAVING t1.PAPERID NOT IN(";
        $sql .= " SELECT DISTINCT PAPERID FROM PAPERS WHERE FLAG = 'imported')) t3 GROUP BY rvid, year";

        if ($year) {
            $sql .= " HAVING year = $year";
        }


        return $sql;

    }

    public function getTotalNumberOfPapersByStatusSql(bool $isSubmittedSameYear = true, $as = 'totalNumberOfPapersAccepted', int $status = 4): string
    {
        $papers = 'PAPERS';
        $paperLog = 'PAPER_LOG';

        $year = !$isSubmittedSameYear ? "$papers.SUBMISSION_DATE" : "pl.DATE";


        $sql = "SELECT $papers.RVID AS rvid, YEAR($year) AS `year`, COUNT(DISTINCT($papers.PAPERID)) AS $as FROM $paperLog pl JOIN $papers ON $papers.PAPERID = pl.PAPERID AND YEAR($papers.SUBMISSION_DATE) = YEAR(pl.DATE)
                WHERE pl.status IS NOT NULL AND pl.status = $status AND $papers.FLAG = 'submitted'
                GROUP BY rvid, `year`
                ORDER BY rvid, `year` DESC
                ";

        return $sql;

    }

    public function totalNumberOfPapersByStatus(bool $isSubmittedSameYear = true, $as = Stats::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Paper::STATUS_STRICTLY_ACCEPTED): ?Statement
    {

        $conn = $this->getEntityManager()->getConnection();

        try {
            return $conn->prepare($this->getTotalNumberOfPapersByStatusSql($isSubmittedSameYear, $as, $status));

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

        $delay = [];
        $status = $operationName !== 'median-submission-publication' ? Paper::STATUS_STRICTLY_ACCEPTED : Paper::STATUS_PUBLISHED;

        $betweenDetails = [$status];

        if ($operationName === 'median-submission-acceptance') {
            $betweenDetails[] = Paper::STATUS_TMP_VERSION_ACCEPTED;
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect(sprintf('ABS(TIMESTAMPDIFF(%s, MIN(spl.date), Max(bpl.date))) AS delay', $unit));

        $qb->from(PaperLog::class, 'spl');

        if ($rvId) {
            $qb->andWhere('spl.rvid = :rvid')->setParameter('rvid', $rvId);
        }

        if ($years) {
            $this->andOrExp($qb, 'YEAR(spl.date)', $years);
        }

        if ($startAfterDate) {
            $qb->andWhere('spl.date > :date')->setParameter('date', $startAfterDate);
        }

        $qb->andWhere("spl.status IS NOT NULL");
        $qb->andWhere('spl.status = :subStatus')->setParameter('subStatus', Paper::STATUS_SUBMITTED);
        $qb->groupBy('spl.paperid');

        $qb->innerJoin(PaperLog::class, 'bpl', Join::WITH, 'spl.paperid = bpl.paperid');

        if ($rvId) {
            $qb->andWhere('bpl.rvid = :rvid')->setParameter('rvid', $rvId);
        }

        if ($years) {
            $this->andOrExp($qb, 'YEAR(bpl.date)', $years);
        }

        if ($startAfterDate) {
            $qb->andWhere('bpl.date > :date')->setParameter('date', $startAfterDate);
        }

        $qb->andWhere("bpl.status IS NOT NULL");
        $this->andOrExp($qb, 'bpl.status', $betweenDetails);
        $qb->groupBy('bpl.paperid');

        $qb->innerJoin(Paper::class, 'p', Join::WITH, 'spl.paperid = p.paperid AND p.flag =:flag')->setParameter('flag', 'submitted');
        if ($rvId) {
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
        }

        if ($years) {
            $this->andOrExp($qb, 'YEAR(p.submissionDate)', $years);
        }

        if ($startAfterDate) {
            $qb->andWhere('p.submissionDate > :date')->setParameter('date', $startAfterDate);
        }
        $qb->addGroupBy('p.paperid');

        foreach ($qb->getQuery()->getArrayResult() as $values) {
            $delay[] = $values['delay'];
        }

        try {
            $median = $this->getMedian($delay);

        } catch (\LengthException $e) {
            $this->logger->critical($e->getMessage());
            $median = null;
        }

        return $median;

    }

    private  function commonQuery(int $rvId = null, array $years = [], string $startAfterDate = null, int|array $status = [Paper::STATUS_STRICTLY_ACCEPTED], bool $ignoreImportedArticles = false): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect("COUNT(DISTINCT(pl.paperid)) AS total");

        if (!empty($years)) {
            $qb->addSelect("YEAR(pl.date) As year");
        }

        $qb->from(PaperLog::class, 'pl')
            ->innerJoin(Paper::class, 'p', Join::WITH, 'pl.paperid = p.paperid')
            ->andWhere('p.status != :deleted')->setParameter('deleted', Paper::STATUS_DELETED)
            ->andWhere('p.status != :removed')->setParameter('removed', Paper::STATUS_REMOVED)
            ->andWhere("pl.status IS NOT NULL");


        if (is_array($status)) {
            $this->andOrExp($qb, 'pl.status', $status);

        } else {
            $qb->andWhere('pl.status = :status')->setParameter('status', $status);
        }


        if ($ignoreImportedArticles){
            $qb->andWhere("p.flag = :flag");
            $qb->setParameter('flag', 'submitted');
        }

        if (self::isValidDate($startAfterDate)) {
            $qb->andWhere('p.submissionDate > :date')->setParameter('date', $startAfterDate);
        }

        if ($rvId) {
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
        }

        return $qb;

    }


    public function getAccepted(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true): float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED], $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }

    public function getRefused(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = false): float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_REFUSED, $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }

    private function processResult(\Doctrine\ORM\QueryBuilder $qb, array $years = []): float{
        $total = 0;

        if (empty($years)) {
            try {
                return $qb->getQuery()->getSingleScalarResult();
            } catch (NoResultException|NonUniqueResultException  $e) {
                $this->logger->critical($e->getMessage());
            }
        }  else {


                $this->andOrExp($qb, 'YEAR(pl.date)', $years);
                $qb->addGroupBy('year');

            $result = $qb->getQuery()->getResult();

            foreach ($result as $values) {
                $total += $values['total'];
            }

        }

        return $total;

    }

    public function getPublished(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = false) :float{
        $qb = $this->commonQuery($rvId, $years, $startAfterDate, Paper::STATUS_PUBLISHED, $ignoreImportedArticles);
        return $this->processResult($qb, $years);

    }



    public function getAllAcceptedNotYetPublished(int $rvId = null, array $years = [], string $startAfterDate = null, $ignoreImportedArticles = true) : float
    {

        $qb = $this->commonQuery($rvId, $years, $startAfterDate, [Paper::STATUS_STRICTLY_ACCEPTED, Paper::STATUS_TMP_VERSION_ACCEPTED], $ignoreImportedArticles);

        $subQb = $this->getEntityManager()->createQueryBuilder();


        // accepted and published
        $subQb->addSelect('1')
            ->from(PaperLog::class, 'pl1')
            ->andWhere('pl1.paperid = pl.paperid')
            ->andWhere('pl1.status IS NOT NULL')
            ->andWhere('pl1.status = :pStatus');


        $qb->andWhere($qb->expr()->not($qb->expr()->exists( $subQb->getDQL())))->setParameter('pStatus', Paper::STATUS_PUBLISHED);;

        return $this->processResult($qb, $years);

    }

}
