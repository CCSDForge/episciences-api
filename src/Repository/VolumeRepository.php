<?php

namespace App\Repository;

use App\Entity\Volume;
use App\Resource\Rang;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Volume>
 */
class VolumeRepository extends ServiceEntityRepository implements RangeInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Volume::class);
    }

    public function getRange(int|string $journalIdentifier = null): array
    {
        $qb = $this->createQueryBuilder('v');
        $qb->distinct();
        $qb->select("v.vol_year AS year");

        if ($journalIdentifier) {
            $qb->andWhere('v.rvid = :rvId');
            $qb->setParameter('rvId', $journalIdentifier);
        }

        $qb->orderBy('year', 'DESC');

        return $qb->getQuery()->getResult();

    }
}
