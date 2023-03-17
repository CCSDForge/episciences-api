<?php

namespace App\Repository;

use App\Entity\PaperConflicts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaperConflicts>
 *
 * @method PaperConflicts|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaperConflicts|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaperConflicts[]    findAll()
 * @method PaperConflicts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaperConflictsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaperConflicts::class);
    }

    public function save(PaperConflicts $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaperConflicts $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PaperConflicts[] Returns an array of PaperConflicts objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PaperConflicts
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
