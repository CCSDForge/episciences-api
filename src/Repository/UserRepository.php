<?php
declare(strict_types=1);

namespace App\Repository;

use App\AppConstants;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Resource\UsersStatsOutput;
use App\Traits\ToolsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

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

    public const AVAILABLE_FILTERS = [AppConstants::WITH_DETAILS];
    public const USER_ALIAS = 'u';

    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, User::class);
        $this->logger = $logger;
    }

    public function getUserStats(array $filters): UsersStatsOutput
    {
        $rvId = null;

        if (array_key_exists('rvid', $filters)) {
            $rvId = (int)$filters['rvid'];
        }

        $uid = array_key_exists('uid', $filters) ? (int)$filters['uid'] : null;
        $role = (array_key_exists('role', $filters) && !empty($filters['role'])) ? $filters['role'] : null;
        $registrationYear = array_key_exists('registrationDate', $filters) ? (int)$filters['registrationDate'] : null;
        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters);

        $statResource = new UsersStatsOutput();
        $statResource->setAvailableFilters(self::AVAILABLE_FILTERS);
        $statResource->setRequestedFilters($filters);
        $statResource->setName('nbUsers');

        $userStatsQuery = $this->findByReviewQuery($rvId, $uid, $role, $withDetails, $registrationYear)->getQuery();
        $userStats = $userStatsQuery->getArrayResult();


        try {
            $nbUsers = (int)$this->findByReviewQuery($rvId, $uid, $role, false, $registrationYear)->getQuery()->getSingleScalarResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $nbUsers = null;
            $this->logger->error($e->getMessage());
        }

        $details = null;

        if ($withDetails) {

            if ($rvId && !$role) {

                $rvIdResult = $this->applyFilterBy($userStats, 'rvid', (string)$rvId);

                if (array_key_exists($rvId, $rvIdResult)) {
                    $details = $this->reformatData($rvIdResult[$rvId]);
                    $statResource->setDetails($details);
                }

            } elseif (!$rvId && $role) {
                $roleResult = $this->applyFilterBy($userStats, 'role', $role);
                $statResource->setDetails($roleResult);
            } elseif ($rvId && $role) {

                $details = $this->applyFilterBy($userStats, 'rvid', (string)$rvId);

                if (array_key_exists($rvId, $details)) {
                    $details = $this->reformatData($details[$rvId]);
                }

                $roleResult = array_key_exists($role, $details) ? $details[$role] : [];
                $statResource->setDetails($roleResult);

            }

            $details = array_key_exists($rvId, $this->reformatData($userStats)) ?
                $this->reformatData($userStats)[$rvId] :
                $this->reformatData($userStats) ;
        }

        $statResource->setValue($nbUsers);
        $statResource->setDetails($details);

        return $statResource;
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
        $userRolesRepo = $this->getEntityManager()->getRepository(UserRoles::class);
        return $userRolesRepo->getUserRolesStatsQuery($rvId, $uid, $role, $withDetails);
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

    private function reformatData(array $array): array
    {
        $rvId = null;
        $role = null;
        $nbUsers = 0;
        $result = [];

        foreach ($array as $key => $value) {

            foreach ($value as $k => $v) {

                if ($k === 'rvid' && $v !== $rvId) {
                    $rvId = $v;
                }

                if ($k === 'role' && $v !== $role) {
                    $role = $v;
                    $nbUsers = 0;
                }

                if ($k === 'nbUsers') {
                    $nbUsers += $v;
                }


                if ($rvId === null && null !== $role) {
                    $result[$role]['nbUsers'] = $nbUsers;

                } elseif(null !== $role) {
                    $result[$rvId][$role]['nbUsers'] = $nbUsers;

                }

            }
        }
        return $result;
    }
}
