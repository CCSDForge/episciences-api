<?php

namespace App\Repository\Customer;

use App\Entity\Customer\TUtilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;


class TUtilisateursRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    private ObjectManager $objectManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TUtilisateurs::class);
        $this->objectManager = $registry->getManager('customer');

    }

    /**
     * @param string $username
     * @return TUtilisateurs|null
     * @throws NonUniqueResultException
     */
    public function loadUserByUsername(string $username): ?TUtilisateurs
    {
        $sql = "SELECT u.UID, u.USERNAME, u.EMAIL, u.CIV, u.FIRSTNAME, u.MIDDLENAME, u.LASTNAME, u.TIME_REGISTERED, u.TIME_MODIFIED, u.VALID FROM App\Entity\Customer\TUtilisateurs u WHERE u.username = :query";
        /** @var Query $query */
        $query = $this->objectManager->createQuery($sql);
        return $query
            ->setParameter('query', $username)
            ->getOneOrNullResult();
    }
}
