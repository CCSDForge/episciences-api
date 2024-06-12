<?php

namespace App\Repository;

use App\Entity\News;
use App\Resource\Years;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository implements YearsInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }


    public function getAvailableYears(): array
    {
        $years = [];
        $qb = $this->createQueryBuilder('n');
        $qb->distinct();
        $qb->select("YEAR(n.date_creation) AS year");
        $qb->orderBy('year', 'ASC');

        foreach ($qb->getQuery()->getResult() as $value) {
            $years[] = $value['year'];
        }

        return (array)(new Years())->setValues($years);
    }
}
