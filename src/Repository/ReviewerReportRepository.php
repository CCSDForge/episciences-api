<?php

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\ReviewerReport;
use App\Traits\QueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\ReviewerReport>
 */
class ReviewerReportRepository extends ServiceEntityRepository
{
    use QueryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewerReport::class);
    }

    /**
     * Date of a report: its latest save (UPDATE_DATE), which is its validation for a completed report
     * (STATUS = 2, a report reopened then validated again gets the new date); its creation if it was saved once.
     */
    public const string REPORT_DATE = 'COALESCE(r.updateDate, r.creationDate)';

    /**
     * The reports of the reviewers: one row by paper version (docid) and by reviewer, with the paper (paperid),
     * filtered on the year and the start date of the report (REPORT_DATE)
     */
    public function getReceivedReports(array $options = []): QueryBuilder
    {
        $rvId = $options['rvid'] ?? null;
        $status = $options['report-status'] ?? null;
        $docId = $options['docId'] ?? null;
        $uid = $options['uid'] ?? null;

        $years = $options['year'] ?? null;
        $startAfterDate = $options[AppConstants::START_AFTER_DATE] ?? null;

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->from(ReviewerReport::class, 'r');
        //$qb->addSelect('p.rvid');
        $qb->addSelect('r.docid');
        $qb->addSelect('p.paperid');
        $qb->addSelect('COUNT(r.docid) as count');

        //$qb->addSelect('r.uid');
        //$qb->addSelect('r.status');
        //$qb->addSelect('YEAR(r.creationDate)');


        if($rvId){
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
        } else {
            $this->andActiveJournals($qb, 'p.rvid');
        }

        if($status){
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        if($docId){
            $qb->andWhere('r.docid = :docId')->setParameter('docId', $docId);
        }

        if($uid){
            $qb->andWhere('r.uid = :uid')->setParameter('uid', $uid);
        }


        if ($years) {
            $this->andOrExp($qb, sprintf('YEAR(%s)', self::REPORT_DATE), $years);
        }

        if ($startAfterDate) {
            $qb->andWhere(sprintf('%s >= :startAfterDate', self::REPORT_DATE))->setParameter('startAfterDate', $startAfterDate);
        }

        $qb->innerJoin(Paper::class, 'p', Join::ON, 'p.docid = r.docid');

        $qb->orderBy('p.rvid', \SortDirection::Descending);
        $qb->addOrderBy('r.docid', \SortDirection::Descending);
        $qb->addOrderBy('r.uid', \SortDirection::Descending);

        $qb->groupBy('p.rvid');
        $qb->addGroupBy('r.status');
        $qb->addGroupBy('r.docid');
        $qb->addGroupBy('p.paperid');
        $qb->addGroupBy('r.uid');

        return $qb;

    }

}
