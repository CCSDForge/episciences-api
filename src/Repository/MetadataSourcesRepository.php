<?php

namespace App\Repository;

use App\Entity\MetadataSources;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MetadataSources>
 *
 * @method MetadataSources|null find($id, $lockMode = null, $lockVersion = null)
 * @method MetadataSources|null findOneBy(array $criteria, array $orderBy = null)
 * @method MetadataSources[]    findAll()
 * @method MetadataSources[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MetadataSourcesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetadataSources::class);
    }

}
