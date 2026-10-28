<?php

namespace App\Repository;

use App\AppConstants;
use App\Entity\Paper;
use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use App\Traits\QueryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\UserInvitation>
 */
class UserInvitationRepository extends ServiceEntityRepository
{
    use QueryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvitation::class);
    }

    /**
     * The invitations sent to the reviewers (one row by assignment: an answered invitation has several assignments,
     * count the distinct invitations "id"). Without "invitation-status", all of them except the cancelled ones.
     */
    public function getReviewsRequested(array $options = []): QueryBuilder
    {

        $rvId = $options['rvid'] ?? null;
        $invitationStatus = $options['invitation-status'] ?? null;
        $startAfterDate = $options[AppConstants::START_AFTER_DATE] ?? null;

        $docId = $options['docId'] ?? null;
        $uid = $options['uid'] ?? null;
        $years = $options['year'] ?? null;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect('ua.rvid');
        $qb->addSelect('ua.itemid');
        $qb->addSelect('ua.uid');
        $qb->addSelect('i.status');
        $qb->addSelect('i.id');
        $qb->addSelect('i.sendingDate as date');
        $qb->addSelect('i.expirationDate as expirationDate');
        // the paper of the invitation (the declared conflicts of interest are by paper); none if the version was removed
        $qb->addSelect('p.paperid');


        $qb->from(UserAssignment::class, 'ua');
        $qb->innerJoin(UserInvitation::class, 'i', Join::ON, 'ua.invitationId = i.id');
        $qb->leftJoin(Paper::class, 'p', Join::ON, 'p.docid = ua.itemid');

        $qb->andWhere('ua.item =:item')->setParameter('item', UserAssignment::ITEM_PAPER);

        if ($years) {
            $this->andOrExp($qb, 'YEAR(i.sendingDate)', $years);
        }

        if ($startAfterDate) {
            $qb->andWhere('i.sendingDate >= :startAfterDate')->setParameter('startAfterDate', $startAfterDate);
        }

        $qb->andWhere('ua.roleid =:role')->setParameter('role', UserAssignment::ROLE_REVIEWER);

        if ($rvId) {
            $qb->andWhere('ua.rvid = :rvId')->setParameter('rvId', $rvId);
        } else {
            $this->andActiveJournals($qb, 'ua.rvid');
        }

        if ($invitationStatus) {
            $qb->andWhere('i.status = :status')->setParameter('status', $invitationStatus);
        } else {
            $qb->andWhere('i.status != :cancelled')->setParameter('cancelled', UserAssignment::STATUS_CANCELLED);
        }

        if ($docId) {
            $qb->andWhere('ua.itemid = :docId')->setParameter('docId', $docId);
        }

        if ($uid) {
            $qb->andWhere('ua.uid = :uid')->setParameter('uid', $uid);
        }

        $qb->orderBy('ua.rvid', \SortDirection::Descending);
        $qb->addOrderBy('i.id', \SortDirection::Descending);
        $qb->addOrderBy('ua.itemid', \SortDirection::Descending);
        $qb->addOrderBy('ua.uid', \SortDirection::Descending);

        return $qb;

    }
}
