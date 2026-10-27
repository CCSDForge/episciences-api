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
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class AppQueryItemCollectionExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{
    use QueryTrait;

    /** Journal settings restricting the editors (copy editors) to the papers they are assigned to */
    private const string SETTING_ENCAPSULATE_EDITORS = 'encapsulateEditors';
    private const string SETTING_ENCAPSULATE_COPY_EDITORS = 'encapsulateCopyEditors';

    public function __construct(private Security $security)
    {
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


    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                   $operation = null,
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
        ?Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        $context[AppConstants::IS_APP_ITEM] = true;
        $this->addWhere($queryBuilder, $resourceClass, $operation, $context);

    }

    /**
     */
    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, ?HttpOperation $operation = null, array $context = []): void
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


        if ($resourceClass === Review::class) {

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

            // Use the indexed stored generated column instead of JSON_EXTRACT() to avoid
            // full-table scans that triggered sort_buffer_size overflow (MySQL error 1038).
            $queryBuilder->andWhere("$alias.is_public = :isPublic")->setParameter('isPublic', true);

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
                $queryBuilder->addOrderBy(sprintf("%s.publicationDate", $queryBuilder->getRootAliases()[0]), \SortDirection::Descending);
            }
        } else {
            $this->andOrExp($queryBuilder, $field, array_merge(Paper::STATUS_ACCEPTED, [Paper::STATUS_PUBLISHED]));
        }

        return $queryBuilder;

    }

    private function adnWhereAcceptedOnly(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        $this->andOrExp($queryBuilder, sprintf('%s.status', $alias), Paper::STATUS_ACCEPTED);
        $queryBuilder->addOrderBy(sprintf("%s.modificationDate", $alias), \SortDirection::Descending);
        return $queryBuilder;

    }


    private function publicAccessProcess(QueryBuilder $queryBuilder, string $alias, string $resourceClass, array $context = []): void
    {
        /** @var HttpOperation $operation */
        $operation = $context['operation'] ?? null;
        $operationName = $operation?->getName();

        if (in_array($resourceClass, [Paper::class, Volume::class, Section::class], true)) {
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


    /**
     * Statuses of the papers open to everyone, as in offline mode (publicAccessProcess): a published
     * or accepted paper can be read; the list holds the published papers, or the accepted ones only
     * with only_accepted.
     *
     * @return list<int>
     */
    private function publicPaperStatuses(bool $isItem, bool $isOnlyAccepted): array
    {
        if ($isItem) {
            return [...Paper::STATUS_ACCEPTED, Paper::STATUS_PUBLISHED];
        }

        return $isOnlyAccepted ? Paper::STATUS_ACCEPTED : [Paper::STATUS_PUBLISHED];
    }

    /**
     * Scope of the papers listed to a connected user (`scope` query parameter, validated by its schema):
     * the papers opened by their role by default.
     *
     * @param array<string, mixed> $context
     */
    private function paperScope(array $context): string
    {
        $scope = $context['filters'][Paper::SCOPE_PARAM] ?? null;

        return is_string($scope) && in_array($scope, Paper::SCOPES, true) ? $scope : Paper::SCOPE_WORKSPACE;
    }

    /**
     * Applies the journal (rvcode) and publication year filters to the papers visible to a connected user.
     * The papers are already restricted to the journal of the token: a journal other than this one returns no paper.
     */
    private function applyPrivatePaperFilters(QueryBuilder $queryBuilder, string $alias, array $context = []): void
    {
        $requestedRvId = $context['filters']['rvid'] ?? null;

        if ($requestedRvId) {
            $queryBuilder->andWhere("$alias.rvid = :requestedRvId")->setParameter('requestedRvId', $requestedRvId);
        }

        if (isset($context['filters'][AppConstants::YEAR_PARAM])) {
            $years = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
            $this->andOrExp($queryBuilder, sprintf('YEAR(%s.%s)', $alias, 'publicationDate'), $years);
        }
    }

    /**
     * Papers of the journal of the token that the role of the connected user opens to them: every paper
     * for the secretaries (and the roles above) and the editors (copy editors) who are not encapsulated,
     * the assigned papers for the other editorial roles and the reviewers, the papers submitted or
     * co-authored for the others (as isRestrictedAccess in the gpl).
     */
    private function paperAccessCondition(QueryBuilder $queryBuilder, string $alias, User $currentUser): Andx
    {
        $expr = $queryBuilder->expr();
        $access = $expr->andX("$alias.rvid = :rvId");
        $queryBuilder->setParameter('rvId', $currentUser->getCurrentJournalID());

        if ($this->security->isGranted('ROLE_SECRETARY') || $this->isEditorialStaffOfEveryPaper($queryBuilder, $currentUser)) {
            $queryBuilder->orderBy("$alias.when", \SortDirection::Descending);

            return $access;
        }

        if (
            $this->security->isGranted('ROLE_EDITOR') ||
            $this->security->isGranted('ROLE_COPYEDITOR') ||
            $this->security->isGranted('ROLE_GUEST_EDITOR') ||
            $this->security->isGranted('ROLE_REVIEWER')
        ) { // only assigned papers

            // The papers they are currently assigned to, and, for the editors and copy editors, the
            // previous versions of the papers whose latest version they are assigned to
            // (Episciences_Paper::isAssignedEditorOrCopyEditor in the gpl)
            // The paperids of the latest versions the user is currently assigned to as editor or copy
            // editor: computed once from their assignments rather than for every paper of the journal
            $latestVersionsOfUser = $queryBuilder->getEntityManager()->createQueryBuilder()
                ->select('latestVersion.paperid')
                ->from(UserAssignment::class, 'vAss')
                ->join(Paper::class, 'latestVersion', 'WITH', 'latestVersion.docid = vAss.itemid')
                ->where('vAss.item = :type')
                ->andWhere('vAss.uid = :to')
                ->andWhere('vAss.status = :activeAssignment')
                ->andWhere('vAss.roleid IN (:managingRoles)')
                ->andWhere($expr->not($expr->exists($this->laterAssignmentDql($queryBuilder, 'vAss'))))
                ->andWhere($expr->not($expr->exists(
                    $queryBuilder->getEntityManager()->createQueryBuilder()
                        ->select('newerVersion.docid')
                        ->from(Paper::class, 'newerVersion')
                        ->where('newerVersion.paperid = latestVersion.paperid')
                        ->andWhere('newerVersion.when > latestVersion.when')
                        ->getDQL()
                )));

            $queryBuilder
                ->setParameter('type', UserAssignment::ITEM_PAPER)
                ->setParameter('to', $currentUser->getUid())
                ->setParameter('activeAssignment', UserAssignment::STATUS_ACTIVE)
                ->setParameter('managingRoles', [UserAssignment::ROLE_EDITOR, UserAssignment::ROLE_COPY_EDITOR])
                ->orderBy("$alias.when", \SortDirection::Descending);

            $queryBuilder->setParameter('closedStatuses', [Paper::STATUS_DELETED, Paper::STATUS_REMOVED]);

            return $access->add($expr->orX(
                // a deleted or removed paper is only shown by its administration page, to its editors and copy editors
                $expr->andX(
                    "$alias.status NOT IN (:closedStatuses)",
                    $expr->in("$alias.docid", $this->currentAssignmentDql($queryBuilder, 'uAss'))
                ),
                $expr->in("$alias.docid", $this->currentAssignmentDql($queryBuilder, 'eAss', true)),
                $expr->in("$alias.paperid", $latestVersionsOfUser->getDQL())
            ));
        }

        // papers of the author: submitted by them, or of which they are a co-author
        $coAuthorAssignment = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select('coAuthorAss.id')
            ->from(UserAssignment::class, 'coAuthorAss')
            ->where("coAuthorAss.itemid = $alias.docid")
            ->andWhere('coAuthorAss.item = :coAuthorItem')
            ->andWhere('coAuthorAss.roleid = :coAuthorRole')
            ->andWhere('coAuthorAss.uid = :currentUser');

        $queryBuilder
            ->setParameter('currentUser', $currentUser->getUid())
            ->setParameter('coAuthorItem', UserAssignment::ITEM_PAPER)
            ->setParameter('coAuthorRole', UserAssignment::ROLE_CO_AUTHOR)
            ->setParameter('closedStatuses', [Paper::STATUS_DELETED, Paper::STATUS_REMOVED]);

        return $access->add($expr->orX(
            "$alias.user= :currentUser",
            $expr->exists($coAuthorAssignment->getDQL())
        ))->add(
            // the page of a deleted or removed paper is closed to its author (paper/view in the gpl)
            "$alias.status NOT IN (:closedStatuses)"
        );
    }

    /**
     * DQL of the docids of the papers the connected user (:to) is currently assigned to: their latest
     * assignment in a role is active (as Episciences_PapersManager::getEditors() and getReviewers() in
     * the gpl), restricted to the editors and copy editors (:managingRoles) when asked. Computed once
     * from the assignments of the user rather than for every paper of the journal.
     */
    private function currentAssignmentDql(QueryBuilder $queryBuilder, string $assignmentAlias, bool $editorsOnly = false): string
    {
        $expr = $queryBuilder->expr();

        $assignment = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select("$assignmentAlias.itemid")
            ->from(UserAssignment::class, $assignmentAlias)
            ->where("$assignmentAlias.item = :type")
            ->andWhere("$assignmentAlias.uid = :to")
            ->andWhere("$assignmentAlias.status = :activeAssignment")
            ->andWhere($expr->not($expr->exists($this->laterAssignmentDql($queryBuilder, $assignmentAlias))));

        if ($editorsOnly) {
            $assignment->andWhere("$assignmentAlias.roleid IN (:managingRoles)");
        }

        return $assignment->getDQL();
    }

    /**
     * DQL of the assignments of the same user, paper and role made after the given one: when there is
     * none, the given assignment is the latest one.
     */
    private function laterAssignmentDql(QueryBuilder $queryBuilder, string $assignmentAlias): string
    {
        $later = "later_$assignmentAlias";

        return $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select("$later.id")
            ->from(UserAssignment::class, $later)
            ->where("$later.itemid = $assignmentAlias.itemid")
            ->andWhere("$later.item = $assignmentAlias.item")
            ->andWhere("$later.uid = $assignmentAlias.uid")
            ->andWhere("$later.roleid = $assignmentAlias.roleid")
            ->andWhere("$later.when > $assignmentAlias.when")
            ->getDQL();
    }

    /**
     * Whether the editor (copy editor) reads every paper of the journal of the token, assigned or not:
     * when the journal explicitly sets their encapsulation to "0", a setting never saved counting as
     * restrictive (DefaultController::isEditorialStaffOfEveryPaper in the gpl).
     */
    private function isEditorialStaffOfEveryPaper(QueryBuilder $queryBuilder, User $currentUser): bool
    {
        $isEditor = $this->security->isGranted('ROLE_EDITOR');
        $isCopyEditor = $this->security->isGranted('ROLE_COPYEDITOR');

        if (!$isEditor && !$isCopyEditor) {
            return false;
        }

        /** @var Review|null $journal */
        $journal = $queryBuilder->getEntityManager()->getRepository(Review::class)->findOneBy(['rvid' => $currentUser->getCurrentJournalID()]);

        return
            ($isEditor && $journal?->getSetting(self::SETTING_ENCAPSULATE_EDITORS) === '0') ||
            ($isCopyEditor && $journal?->getSetting(self::SETTING_ENCAPSULATE_COPY_EDITORS) === '0');
    }

    private function privateAccessProcess(
        QueryBuilder  $queryBuilder,
        string        $alias,
        string        $resourceClass,
        User          $currentUser,
        ?HttpOperation $operation = null,
                      array $context = []
    ): void
    {
        $isOnlyAccepted = isset($context['filters']['only_accepted']) && filter_var($context['filters']['only_accepted'], FILTER_VALIDATE_BOOLEAN);

        // @see operation security allowed only for ROLE_SECRETARY
        if ($resourceClass === User::class) {
            $queryBuilder->
            join(UserRoles::class, 'ur', 'ON', "$alias.uid = ur.uid")
                ->andWhere("ur.roleid!= :epiAdminRole")->setParameter('epiAdminRole', User::ROLE_ROOT)
                ->andWhere("$alias.uid!= :systemUid")->setParameter('systemUid', User::EPISCIENCES_UID)
                ->andWhere("ur.rvid= :userVid")->setParameter('userVid', $currentUser->getCurrentJournalID());
        } elseif (new \ReflectionClass($resourceClass)->implementsInterface(UserOwnedInterface::class)) {

            if ($resourceClass === Paper::class) {

                $isItem = isset($context[AppConstants::IS_APP_ITEM]) && filter_var($context[AppConstants::IS_APP_ITEM], FILTER_VALIDATE_BOOLEAN);
                $scope = $isItem ? Paper::SCOPE_ALL : $this->paperScope($context);

                if ($scope === Paper::SCOPE_PUBLIC) { // the papers listed in offline mode
                    $this->publicAccessProcess($queryBuilder, $alias, $resourceClass, $context);
                    return;
                }

                if ($isOnlyAccepted) {
                    $this->adnWhereAcceptedOnly($queryBuilder, $alias);
                }

                $access = $this->paperAccessCondition($queryBuilder, $alias, $currentUser);

                if ($scope === Paper::SCOPE_ALL) {
                    // The papers open to everyone (as isRestrictedAccess in the gpl), whatever the journal of
                    // the token, and those opened by the role; the confidential fields stay protected by the voter.
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX("$alias.status IN (:publicStatuses)", $access))
                        ->setParameter('publicStatuses', $this->publicPaperStatuses($isItem, $isOnlyAccepted));

                    if (!$isItem) {
                        $queryBuilder
                            ->orderBy("$alias.publicationDate", \SortDirection::Descending)
                            ->addOrderBy("$alias.when", \SortDirection::Descending);
                    }
                } else {
                    $queryBuilder->andWhere($access);
                }

                $this->applyPrivatePaperFilters($queryBuilder, $alias, $context);

            }


        } elseif ($resourceClass === Volume::class || $resourceClass === Section::class) {

            if ($this->security->isGranted('ROLE_EDITOR')) {

                if (
                    $operation instanceof \ApiPlatform\Metadata\HttpOperation &&
                    (
                        str_starts_with((string) $operation->getUriTemplate(), Volume::DEFAULT_URI_TEMPLATE) ||
                        str_starts_with((string) $operation->getUriTemplate(), Section::DEFAULT_URI_TEMPLATE)
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
