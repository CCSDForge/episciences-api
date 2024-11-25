<?php

namespace App\Repository;

use App\Entity\News;
use App\Resource\Range;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository implements RangeInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }


    public function getRange(string|int $journalIdentifier = null): array
    {
        $qb = $this->createQueryBuilder('n');
        $qb->distinct();
        $qb->select("YEAR(n.date_creation) AS year");


        if($journalIdentifier){
            $qb->andWhere('n.rvcode = :code');
            $qb->setParameter('code',$journalIdentifier);
        }

        $qb->orderBy('year', 'DESC');

        return $qb->getQuery()->getResult();

    }
}
