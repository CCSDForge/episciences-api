<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */

class UserRepository extends ServiceEntityRepository
{
    use ToolsTrait;

    public const USER_ALIAS = 'u';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);

    }


    /**
     * get users by review query
     * @param int|null $rvId
     * @param null $uid
     * @param string|null $role
     * @param bool $withDetails
     * @param int|null $registrationYear
     * @return QueryBuilder
     */
    public function findByReviewQuery(int $rvId = null, $uid = null, string $role = null, bool $withDetails = false, int $registrationYear = null): QueryBuilder
    {
        return $this->getEntityManager()->getRepository(UserRoles::class)->getUserRolesStatsQuery($rvId, $uid, $role, $withDetails);
    }

    /**
     * TODO requête SQL ultra lente ?
     * nb users by reviewer
     * @param int|null $rvId
     * @param null $uid
     * @param string|null $role
     * @param int|null $registrationYear
     * @return QueryBuilder
     */
    public function countByReviewQuery(int $rvId = null, $uid = null, string $role = null, int $registrationYear = null): QueryBuilder
    {
        $userAlias = self::USER_ALIAS;
        $userAlias1 = self::USER_ALIAS . 1;
        $userRolesAlias = UserRolesRepository::USER_ROLES_ALIAS;

        $qb = $this
            ->createQueryBuilder($userAlias1);

        $exp = $qb->expr();

        $qb
            ->select("$userRolesAlias.rvid, YEAR($userAlias1.registrationDate) AS year, $userRolesAlias.roleid as role, count(DISTINCT ($userAlias1.uid) ) as nbUsers, $userAlias1.uid")
            ->from(User::class, $userAlias)
            ->innerJoin(UserRoles::class, $userRolesAlias, Join::WITH, sprintf('%s.uid = %s.uid', $userAlias1, $userRolesAlias));


        if ($rvId !== null) {
            $qb->andWhere("$userRolesAlias.rvid =:rvId");
            $qb->setParameter('rvId', $rvId);
        }

        if (null !== $uid) {

            $orExp = $exp->orX();
            foreach ((array)$uid as $id) {
                $orExp->add($exp->orX($exp->eq(sprintf('%s.uid', $userAlias1), $id)));
            }

            $qb->andWhere($orExp);

        }

        if (null !== $role) {

            $qb
                ->andWhere(sprintf('%s.roleid = :roleId', $userRolesAlias))
                ->setParameter('roleId', $role);
        }

        if (null !== $registrationYear) {

            $qb
                ->andWhere(sprintf('YEAR(%s.registrationDate) = :registrationYear', $userAlias1))
                ->setParameter('registrationYear', $registrationYear);
        }

        if (!$rvId) {
            $qb->groupBy("$userRolesAlias.rvid");
        }

        if ($registrationYear) {
            $qb->groupBy('year');
        }

        $qb->addGroupBy("$userRolesAlias.roleid");
        $qb->addGroupBy("$userAlias1.uid");
        return $qb;
    }
}
