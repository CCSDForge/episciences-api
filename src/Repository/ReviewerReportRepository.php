<?php

namespace App\Repository;

use App\Entity\Paper;
use App\Entity\ReviewerReport;
use App\Traits\QueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class ReviewerReportRepository extends ServiceEntityRepository
{
    use QueryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewerReport::class);
    }

    public function getReceivedReports(array $options = []): QueryBuilder
    {
        $rvId = $options['rvid'] ?? null;
        $status = $options['report-status'] ?? null;
        $docId = $options['docId'] ?? null;
        $uid = $options['uid'] ?? null;

        $years = $options['year'] ?? null;

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->from(ReviewerReport::class, 'r');
        //$qb->addSelect('p.rvid');
        $qb->addSelect('r.docid');
        $qb->addSelect('COUNT(r.docid) as count');

        //$qb->addSelect('r.uid');
        //$qb->addSelect('r.status');
        //$qb->addSelect('YEAR(r.creationDate)');


        if($rvId){
            $qb->andWhere('p.rvid = :rvid')->setParameter('rvid', $rvId);
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
            $this->andOrExp($qb, 'YEAR(r.creationDate)', $years);
        }

        $qb->innerJoin(Paper::class, 'p', Join::WITH, 'p.docid = r.docid');

        $qb->orderBy('p.rvid', 'DESC');
        $qb->orderBy('r.docid', 'DESC');
        $qb->orderBy('r.uid', 'DESC');

        $qb->groupBy('p.rvid');
        $qb->addGroupBy('r.status');
        $qb->addGroupBy('r.docid');
        $qb->addGroupBy('r.uid');

        return $qb;

    }

}
