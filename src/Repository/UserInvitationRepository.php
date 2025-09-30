<?php

namespace App\Repository;

use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use App\Traits\QueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class UserInvitationRepository extends ServiceEntityRepository
{
    use QueryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvitation::class);
    }

    public function getReviewsRequested(array $options = []): QueryBuilder
    {

        $rvId = $options['rvid'] ?? null;
        $invitationStatus = $options['invitation-status'] ?? null;
        $docId = $options['docId'] ?? null;
        $uid = $options['uid'] ?? null;
        $years = $options['year'] ?? null;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect('ua.rvid');
        $qb->addSelect('ua.itemid');
        $qb->addSelect('ua.uid');
        $qb->addSelect('i.status');
        $qb->addSelect('i.id');
        $qb->addSelect('MAX(i.sendingDate) as date');

        if ($years) {
            $this->andOrExp($qb, 'YEAR(i.sendingDate)', $years);
        }

        $qb->from(UserInvitation::class, 'i');
        $qb->innerJoin(UserInvitation::class, 'ii', Join::WITH, 'i.sendingDate = ii.sendingDate');
        $qb->innerJoin(UserAssignment::class, 'ua', Join::WITH, 'ua.invitationId = i.id');
        $qb->andWhere('ua.item =:item')->setParameter('item', UserAssignment::ITEM_PAPER);
        $qb->andWhere('ua.roleid =:role')->setParameter('role', UserAssignment::ROLE_REVIEWER);
        $qb->orderBy('ua.rvid', 'DESC');
        $qb->addOrderBy('i.id', 'DESC');
        $qb->addOrderBy('ua.itemid', 'DESC');
        $qb->addOrderBy('ua.uid', 'DESC');
        $qb->addGroupBy('ua.rvid');
        $qb->addGroupBy('ua.itemid');
        $qb->addGroupBy('ua.uid');
        $qb->addGroupBy('i.status');
        $qb->addGroupBy('i.id');

        if ($rvId) {
            $qb->andhaving('ua.rvid = :rvId')->setParameter('rvId', $rvId);
        }

        if ($invitationStatus) {
            $qb->andHaving('i.status = :status')->setParameter('status', $invitationStatus);
        }

        if ($docId) {
            $qb->andHaving('ua.itemid = :docId')->setParameter('docId', $docId);
        }

        if ($uid) {
            $qb->andHaving('ua.uid = :uid')->setParameter('uid', $uid);
        }

        return $qb;

    }
}
