<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Main\Papers;
use App\Entity\Main\User;
use App\Entity\Main\UserRoles;
use App\Entity\UserOwnedInterface;
use Doctrine\ORM\QueryBuilder;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param Operation|null $operation
     * @param array $context
     * @return void
     * @throws ReflectionException
     */

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);

    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param array $identifiers
     * @param Operation|null $operation
     * @param array $context
     * @return void
     * @throws ReflectionException
     */
    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);

    }

    /**
     * @throws ReflectionException
     */
    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {


        if ($this->security->isGranted('ROLE_EPIADMIN')) {
            return;
        }


        $alias = $queryBuilder->getRootAliases()[0];

        /** @var User $curentUser */
        $curentUser = $this->security->getUser();


        if ($curentUser) { // connected

            if ($resourceClass === User::class) {
                $queryBuilder->
                join(UserRoles::class, 'ur', 'WITH', "$alias.uid = ur.uid")->
                andWhere("ur.roleid!= :epiAdminRole")->setParameter('epiAdminRole', User::ROLE_ROOT)->
                andWhere("$alias.uid!= :systemUid")->setParameter('systemUid', User::EPISCIENCES_UID)->
                andWhere("ur.rvid= :userVid")->setParameter('userVid', $curentUser->rvId);
            } elseif ((new \ReflectionClass($resourceClass))->implementsInterface(UserOwnedInterface::class)) {

                $queryBuilder->
                andWhere("$alias.user= :currentUser")->
                setParameter('currentUser', $curentUser->getUid());

            }


        } elseif ($resourceClass === Papers::class) {
            $queryBuilder->
            andWhere("$alias.status= :publishedOnly")->
            setParameter('publishedOnly', Papers::STATUS_PUBLISHED);

        }


    }

}