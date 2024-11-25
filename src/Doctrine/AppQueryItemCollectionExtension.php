<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\News;
use App\Entity\Page;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\Section;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Entity\UserRoles;
use App\Entity\UserOwnedInterface;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Traits\QueryTrait;
use Doctrine\ORM\QueryBuilder;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Bundle\SecurityBundle\Security;

class AppQueryItemCollectionExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{
    use QueryTrait;

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
     * @throws ResourceNotFoundException
     */

    #[NoReturn]
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        $context[AppConstants::IS_APP_COLLECTION] = true;

        $rvCode = $context['filters']['rvcode'] ?? null;

        if ($rvCode) {
            $journal = $queryBuilder->getEntityManager()->getRepository(Review::class)->getJournalByIdentifier($rvCode);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $rvCode));
            }

            $context['filters']['rvid'] = $journal->getRvid();

        }

        $this->addWhere($queryBuilder, $resourceClass, $operation, $context);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param array $identifiers
     * @param Operation|null $operation
     * @param array $context
     * @return void
     */
    public function applyToItem(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        array                       $identifiers,
        Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        $context[AppConstants::IS_APP_ITEM] = true;
        $this->addWhere($queryBuilder, $resourceClass, $operation, $context);

    }

    /**
     */
    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, HttpOperation $operation = null, array $context = []): void
    {
        $context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE] = true;
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $alias = $queryBuilder->getRootAliases()[0];


        if ($currentUser) { // connected

            if (!$currentUser->getCurrentJournalID()) {

                $this->publicAccessProcess($queryBuilder, $alias, $resourceClass, $context);

            } else {

                $this->privateAccessProcess($queryBuilder, $alias, $resourceClass, $currentUser, $operation, $context);
            }


        } else {
            $this->publicAccessProcess($queryBuilder, $alias, $resourceClass, $context);
        }


        if ($resourceClass === Volume::class | $resourceClass === Section::class) {


            if ($resourceClass === Volume::class) {

                if (isset($context['filters'][AppConstants::YEAR_PARAM])) {
                    $volYear = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
                    $this->processOrExpression($queryBuilder, $alias, $volYear, $resourceClass);
                }

                if ((isset($context['filters']['type']) && $context['filters']['type'])) {
                    $tFilters = (array)$context['filters']['type'];
                    $volType = $this->processTypes($queryBuilder, $tFilters);

                    $tFilters = array_merge($tFilters, [$volType]);

                    if ('' !== $volType) {
                        $this->andOrExp($queryBuilder, sprintf('%s.vol_type', $alias), $tFilters);
                    }
                }

            }

        } elseif ($resourceClass === Review::class) {

            $queryBuilder
                ->andWhere("$alias.rvid!= :portal")
                ->setParameter('portal', Review::PORTAL_ID)
                ->andWhere("$alias.status!= :status")
                ->setParameter('status', Review::STATUS_DISABLED);

        } elseif ($resourceClass === Page::class || $resourceClass === News::class) {

            if (($resourceClass === News::class) && isset($context['filters'][AppConstants::YEAR_PARAM])) {

                $newsYear = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
                $this->processOrExpression($queryBuilder, $alias, $newsYear, $resourceClass);
            }

            $queryBuilder->andWhere("JSON_EXTRACT ($alias.visibility, '$[0]') = :visibility")->setParameter('visibility', 'public');

        } elseif ($resourceClass === Paper::class) {

            $types = isset($context['filters']['type']) ? (array)($context['filters']['type']) : [];
            $this->andOrExp($queryBuilder, sprintf("JSON_EXTRACT(%s.type, '$.title')", $alias), $types);

        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $field
     * @param string $resourceClass
     * @param array $context
     * @return QueryBuilder
     */

    private function adnWherePublished(QueryBuilder $queryBuilder, string $field, string $resourceClass, array $context = []): QueryBuilder
    {

        $allowBrowseAcceptedDocuments = isset($context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE]) && filter_var($context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE], FILTER_VALIDATE_BOOLEAN);
        $isItem = isset($context[AppConstants::IS_APP_ITEM]) && filter_var($context[AppConstants::IS_APP_ITEM], FILTER_VALIDATE_BOOLEAN);

        $strict = !$isItem || !$allowBrowseAcceptedDocuments;

        $parameters = $queryBuilder->getParameters()->getValues();

        if (!empty($parameters) && $resourceClass === Paper::class) {
            $docId = $parameters[array_key_first($parameters)]->getValue();

            if ($docId) {
                $alias = $queryBuilder->getRootAliases()[0];

                $queryBuilder->orWhere(sprintf('%s.paperid = :paperId', $alias))
                    ->setParameter('paperId', $docId);

            }

        }

        if ($strict) {
            $queryBuilder->
            andWhere("$field= :published")->
            setParameter('published', Paper::STATUS_PUBLISHED);
            if ($resourceClass === Paper::class) {
                $queryBuilder->addOrderBy(sprintf("%s.publicationDate", $queryBuilder->getRootAliases()[0]), 'DESC');
            }
        } else {
            $this->andOrExp($queryBuilder, $field, array_merge(Paper::STATUS_ACCEPTED, [Paper::STATUS_PUBLISHED]));
        }

        return $queryBuilder;

    }

    private function adnWhereAcceptedOnly(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        $this->andOrExp($queryBuilder, sprintf('%s.status', $alias), Paper::STATUS_ACCEPTED);
        $queryBuilder->addOrderBy(sprintf("%s.modificationDate", $alias), 'DESC');
        return $queryBuilder;

    }


    private function publicAccessProcess(QueryBuilder $queryBuilder, string $alias, string $resourceClass, array $context = []): void
    {
        /** @var HttpOperation $operation */
        $operation = $context['operation'] ?? null;
        $operationName = $operation?->getName();

        if ($resourceClass === Paper::class || $resourceClass === Volume::class || $resourceClass === Section::class) {
            if ($resourceClass === Paper::class) {
                $allowBrowseAcceptedDocuments = isset($context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE]) && filter_var($context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE], FILTER_VALIDATE_BOOLEAN);
                $isOnlyAccepted = isset($context['filters']['only_accepted']) && filter_var($context['filters']['only_accepted'], FILTER_VALIDATE_BOOLEAN);
                $isCollection = isset($context[AppConstants::IS_APP_COLLECTION]) && filter_var($context[AppConstants::IS_APP_COLLECTION], FILTER_VALIDATE_BOOLEAN);

                if ( // for collection
                    $operationName === Paper::COLLECTION_NAME &&
                    $isCollection &&
                    $allowBrowseAcceptedDocuments &&
                    $isOnlyAccepted
                ) {
                    $this->adnWhereAcceptedOnly($queryBuilder, $alias);
                } else {
                    $this->adnWherePublished($queryBuilder, "$alias.status", $resourceClass, $context);

                    if (isset($context['filters'][AppConstants::YEAR_PARAM])) {
                        $year = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
                        $this->andOrExp($queryBuilder, sprintf('YEAR(%s.%s)', $alias, 'publicationDate'), $year);
                    }

                }
            } else {
                $this->adnWherePublished($queryBuilder, 'papers_a1.status', $resourceClass);
            }

            $rvId = $context['filters']['rvid'] ?? null;

            if ($rvId) {
                $queryBuilder->andWhere(sprintf("%s.rvid = :rvId", $alias))->setParameter('rvId', $rvId);
            }

        }
    }


    private function privateAccessProcess(
        QueryBuilder  $queryBuilder,
        string        $alias,
        string        $resourceClass,
        User          $currentUser,
        HttpOperation $operation = null,
                      $context = []
    ): void
    {
        $isOnlyAccepted = isset($context['filters']['only_accepted']) && filter_var($context['filters']['only_accepted'], FILTER_VALIDATE_BOOLEAN);

        // @see operation security allowed only for ROLE_SECRETARY
        if ($resourceClass === User::class) {
            $queryBuilder->
            join(UserRoles::class, 'ur', 'WITH', "$alias.uid = ur.uid")
                ->andWhere("ur.roleid!= :epiAdminRole")->setParameter('epiAdminRole', User::ROLE_ROOT)
                ->andWhere("$alias.uid!= :systemUid")->setParameter('systemUid', User::EPISCIENCES_UID)
                ->andWhere("ur.rvid= :userVid")->setParameter('userVid', $currentUser->getCurrentJournalID());
        } elseif ((new \ReflectionClass($resourceClass))->implementsInterface(UserOwnedInterface::class)) {

            if ($resourceClass === Paper::class) {

                if ($isOnlyAccepted) {
                    $this->adnWhereAcceptedOnly($queryBuilder, $alias);
                }

                if ($this->security->isGranted('ROLE_SECRETARY')) {

                    $queryBuilder
                        ->andWhere("$alias.rvid = :rvId")->setParameter('rvId', $currentUser->getCurrentJournalID())
                        ->orderBy("$alias.when", "DESC");


                } elseif (
                    $this->security->isGranted('ROLE_EDITOR') ||
                    $this->security->isGranted('ROLE_COPY_EDITOR') ||
                    $this->security->isGranted('ROLE_GUEST_EDITOR') ||
                    $this->security->isGranted('ROLE_REVIEWER')
                ) { // only assigned papers

                    $queryBuilder
                        ->join(UserAssignment::class, 'uAss', 'WITH', "$alias.docid = uAss.itemid")
                        ->andWhere("uAss.item = :type")->setParameter('type', 'paper')
                        ->andWhere("$alias.rvid = :rvId")->setParameter('rvId', $currentUser->getCurrentJournalID())
                        ->andWhere("uAss.uid = :to")->setParameter('to', $currentUser->getUid())
                        ->orderBy("$alias.when", "DESC");


                } else { // author's papers

                    $queryBuilder->
                    andWhere("$alias.user= :currentUser")->
                    setParameter('currentUser', $currentUser->getUid());

                }

            }


        } elseif ($resourceClass === Volume::class || $resourceClass === Section::class) {

            if ($this->security->isGranted('ROLE_EDITOR')) {

                if (
                    $operation &&
                    (
                        str_starts_with($operation->getUriTemplate(), Volume::DEFAULT_URI_TEMPLATE) ||
                        str_starts_with($operation->getUriTemplate(), Section::DEFAULT_URI_TEMPLATE)
                    )
                ) {

                    $queryBuilder
                        ->where("$alias.rvid= :rvId")
                        ->setParameter('rvId', $currentUser->getCurrentJournalID());
                }


            } else {

                $this->adnWherePublished($queryBuilder, 'papers_a1.status', $resourceClass);
            }

        }

    }

}