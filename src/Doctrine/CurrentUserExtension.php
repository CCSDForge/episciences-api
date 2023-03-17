<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use App\Entity\Papers;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserRoles;
use App\Entity\UserOwnedInterface;
use App\Entity\Volume;
use Doctrine\ORM\QueryBuilder;
use JetBrains\PhpStorm\NoReturn;
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

    #[NoReturn]
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($queryBuilder, $resourceClass, $operation);
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
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($queryBuilder, $resourceClass, $operation);

    }

    /**
     * @throws ReflectionException
     */
    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, HttpOperation $operation = null): void
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


                if ($curentUser->rvId && $this->security->isGranted('ROLE_EDITOR')) {
                    return;
                }

                $queryBuilder->
                andWhere("$alias.user= :currentUser")->
                setParameter('currentUser', $curentUser->getUid());

            } elseif ($resourceClass === Volume::class) {

                if ($curentUser->rvId && $this->security->isGranted('ROLE_EDITOR')) {

                   if (str_starts_with($operation->getUriTemplate(), '/volumes{._format}')) {

                       $queryBuilder
                           ->where("$alias.rvid= :rvId")
                           ->setParameter('rvId', $curentUser->rvId)
                           ->addOrderBy("$alias.vid", 'DESC');
                   }


                } else {

                    $this->adnWherePublishedOnly($queryBuilder, 'papers_a1.status');
                }

            }


        } elseif ($resourceClass === Papers::class) {

            $this->adnWherePublishedOnly($queryBuilder, "$alias.status")->
            andWhere("$alias.status= :publishedOnly")->
            setParameter('publishedOnly', Papers::STATUS_PUBLISHED);

        } elseif ($resourceClass === Volume::class) {

            $this->adnWherePublishedOnly($queryBuilder, 'papers_a1.status');

        }


        if ($resourceClass === Volume::class) {

            $queryBuilder
                ->orderBy("$alias.rvid", 'DESC')
                ->addOrderBy("$alias.vid", 'DESC')
                ->andWhere("$alias.status!= :status")
                ->setParameter('status', Review::STATUS_DISABLED)
            ;
        }

    }


    private function adnWherePublishedOnly(QueryBuilder $queryBuilder, string $field): QueryBuilder
    {
        return $queryBuilder->
        andWhere("$field= :published")->
        setParameter('published', Papers::STATUS_PUBLISHED);

    }

}