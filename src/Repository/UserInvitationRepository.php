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
     * The invitations sent to the reviewers for a paper, one row by invitation, with its current status. Without
     * "invitation-status", all of them except the cancelled ones.
     *
     * Episciences inserts a USER_INVITATION row on each answer or cancellation of an invitation, with the same AID (the
     * pending assignment created with the invitation): the first row is the invitation (sending and expiration dates),
     * the latest one gives its status. An invitation is also cancelled when an assignment cancelled it without a new row.
     */
    public function getReviewsRequested(array $options = []): QueryBuilder
    {

        $rvId = $options['rvid'] ?? null;
        $invitationStatus = $options['invitation-status'] ?? null;
        $startAfterDate = $options[AppConstants::START_AFTER_DATE] ?? null;

        $years = $options['year'] ?? null;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->addSelect('ua.rvid');
        $qb->addSelect('ua.itemid');
        $qb->addSelect('ua.uid');
        // cancelled by its latest row, or by an assignment cancelled on this row without a new row (Episciences cancelled the
        // invitations without answer of an accepted paper this way); one subquery alias by use, as an alias is unique in DQL
        $isCancelled = static fn(string $alias): string => sprintf(
            '(c.status = :cancelledInvitation OR EXISTS (SELECT %2$s.id FROM %1$s %2$s WHERE %2$s.invitationId = c.id AND %2$s.status = :cancelledAssignment))',
            UserAssignment::class,
            $alias
        );
        $qb->addSelect(sprintf('CASE WHEN %s THEN :cancelledInvitation ELSE c.status END AS status', $isCancelled('cancelledBy')));
        $qb->setParameter('cancelledInvitation', UserInvitation::STATUS_CANCELLED);
        $qb->setParameter('cancelledAssignment', UserAssignment::STATUS_CANCELLED);
        $qb->addSelect('i.id');
        $qb->addSelect('i.sendingDate as date');
        $qb->addSelect('i.expirationDate as expirationDate');
        // the paper of the invitation (the declared conflicts of interest are by paper)
        $qb->addSelect('p.paperid');


        $qb->from(UserAssignment::class, 'ua');
        // the assignment that created the invitation and its first row: one row by invitation
        $qb->innerJoin(UserInvitation::class, 'i', Join::ON, 'i.aid = ua.id AND i.id = ua.invitationId');
        // the latest row of the invitation: its current status
        $qb->innerJoin(UserInvitation::class, 'c', Join::ON, sprintf('c.id = (SELECT MAX(i2.id) FROM %s i2 WHERE i2.aid = ua.id)', UserInvitation::class));
        // an invitation is a review requested for a paper: an invitation without paper (e.g. ITEMID = 0) is not counted,
        // as the reports (see ReviewerReportRepository::getReceivedReports())
        $qb->innerJoin(Paper::class, 'p', Join::ON, 'p.docid = ua.itemid');

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

        if ($invitationStatus === UserInvitation::STATUS_CANCELLED) {
            $qb->andWhere($isCancelled('cancelledWith'));
        } elseif ($invitationStatus) {
            $qb->andWhere(sprintf('c.status = :status AND NOT %s', $isCancelled('cancelledWith')))->setParameter('status', $invitationStatus);
        } else {
            $qb->andWhere(sprintf('NOT %s', $isCancelled('cancelledWith')));
        }

        $qb->orderBy('ua.rvid', \SortDirection::Descending);
        $qb->addOrderBy('i.id', \SortDirection::Descending);
        $qb->addOrderBy('ua.itemid', \SortDirection::Descending);
        $qb->addOrderBy('ua.uid', \SortDirection::Descending);

        return $qb;

    }
}
