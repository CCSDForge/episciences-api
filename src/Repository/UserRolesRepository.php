<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserRoles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method UserRoles|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRoles|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserRoles[]    findAll()
 * @method UserRoles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */

class UserRolesRepository extends ServiceEntityRepository
{
    public const AVAILABLE_FILTERS = ['rvid', 'uid', 'role'];
    public const USER_ROLES_ALIAS = 'ur';

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, UserRoles::class);
        $this->logger = $logger;
    }


    /**
     * @param int|null $rvId
     * @param null $uid
     * @param string|null $role
     * @param bool $withDetails
     * @return QueryBuilder
     */

    public function getUserRolesStatsQuery(int $rvId = null, $uid = null, string $role = null, bool $withDetails = false): QueryBuilder
    {
        $userRolesAlias = self::USER_ROLES_ALIAS;

        $qb = $this->createQueryBuilder(self::USER_ROLES_ALIAS);

        if ($withDetails) {
            $qb->select("$userRolesAlias.rvid, $userRolesAlias.roleid as role, COUNT(DISTINCT($userRolesAlias.uid)) as nbUsers");
        } else {
            $qb->select("COUNT(DISTINCT($userRolesAlias.uid)) as nbUsers");
        }

        $qb->andWhere(sprintf('%s.roleid != :epiAdmin', self::USER_ROLES_ALIAS))
            ->setParameter('epiAdmin', User::ROLE_ROOT);

        if ($rvId !== null) {
            $qb->andWhere("$userRolesAlias.rvid =:rvId");
            $qb->setParameter('rvId', $rvId);
        }

        if (null !== $uid) {
            $exp = $qb->expr();
            $orExp = $exp->orX();

            foreach ((array)$uid as $id) {
                $orExp->add($exp->orX($exp->eq(sprintf('%s.uid', self::USER_ROLES_ALIAS), $id)));
            }

            $qb->andWhere($orExp);

        }

        if (null !== $role) {

            $qb
                ->andWhere(sprintf('%s.roleid = :roleId', self::USER_ROLES_ALIAS))
                ->setParameter('roleId', $role);
        }

        if ($withDetails) {
            $qb->orderBy("$userRolesAlias.rvid", 'ASC');
            $qb->groupBy("$userRolesAlias.rvid");
            $qb->addGroupBy("$userRolesAlias.roleid");
        }

        return $qb;
    }

}
