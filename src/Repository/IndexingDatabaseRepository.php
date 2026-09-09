<?php

namespace App\Repository;

use App\Entity\IndexingDatabase;
use App\Enum\IndexingDatabaseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IndexingDatabase>
 */
class IndexingDatabaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IndexingDatabase::class);
    }

    /**
     * @return IndexingDatabase[]
     */
    public function findByReviewId(int $rvid): array
    {
        return $this->createQueryBuilder('idb')
            ->innerJoin('idb.reviews', 'r')
            ->where('r.rvid = :rvid')
            ->andWhere('idb.status = :status')
            ->setParameter('rvid', $rvid)
            ->setParameter('status', IndexingDatabaseStatus::VALIDATED)
            ->orderBy('idb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
