<?php

namespace App\Repository;

use App\Entity\PaperComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaperComment>
 *
 * @method PaperComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaperComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaperComment[]    findAll()
 * @method PaperComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaperCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaperComment::class);
    }
}









