<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Review|null find($id, $lockMode = null, $lockVersion = null)
 * @method Review|null findOneBy(array $criteria, array $orderBy = null)
 * @method Review[]    findAll()
 * @method Review[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }


    /**
     * @param string|int|null $identifier
     * @param bool $strict [true: only enabled journals]
     * @return Review|null
     */
    public function getJournalByIdentifier(string|int|null $identifier = null, bool $strict = true): ?Review
    {
        $criteria = is_int($identifier) ? ['rvid' => $identifier] : ['code' => $identifier] ;

        if($strict){
            $criteria['status'] = Review::STATUS_ENABLED;
        }

        return $this->findOneBy($criteria);

    }

    /**
     * Returns all enabled journals, ordered by name.
     * The settings collection is eagerly loaded with each journal, so accessing
     * the journal settings does not trigger additional queries.
     *
     * @return Review[]
     */
    public function getActiveJournals(): array
    {
        return $this->findBy(['status' => Review::STATUS_ENABLED], ['name' => 'ASC']);
    }

}
