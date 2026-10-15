<?php

declare(strict_types=1);

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

    /**
     * @return PaperConflicts[]
     */
    public function findByPaperId(int $paperId): array
    {
        return $this->findBy(['paperId' => $paperId]);
    }
}
