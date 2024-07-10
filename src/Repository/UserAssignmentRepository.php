<?php

namespace App\Repository;

use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class UserAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAssignment::class);
    }



    public function findInvitationsQuery(int $docId = null): QueryBuilder
    {

        $qb = $this->createQueryBuilder('ua');
        $qb->join(UserInvitation::class, 'ui', Join::WITH, 'ua.invitationId = ui.id');
        $qb->andWhere('ua.item =:item')->setParameter('item', UserAssignment::ITEM_PAPER);

        if($docId){
            $qb->andWhere('ua.itemid =:docId')->setParameter('docId', $docId);
        }

        $qb->andWhere('ua.roleid =:roleId')->setParameter('roleId', UserAssignment::ROLE_REVIEWER);
        $qb->orderBy('ua.when', 'DESC');
        $qb->addGroupBy('ua.status', 'ASC');
        return $qb;

    }

}
