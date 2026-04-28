<?php

namespace App\Repository;

use App\Entity\JournalSettingNg;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalSettingNg>
 */
class JournalSettingNgRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalSettingNg::class);
    }

    /**
     * @param int $rvId
     * @return float|int|mixed|string|null
     * @throws NonUniqueResultException
     */

    public function getFrontSettings(int $rvId): mixed
    {

        $qb = $this->createQueryBuilder('fs');

        $qb->select('fs.settings');


        $qb->andWhere('fs.rvid= :rvId')->setParameter('rvId', $rvId);

        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);

    }

}
