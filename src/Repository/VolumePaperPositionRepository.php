<?php

namespace App\Repository;

use App\Entity\VolumePaperPosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VolumePaperPosition>
 */
class VolumePaperPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VolumePaperPosition::class);
    }


}
