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
use App\Entity\Section;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Entity\UserRoles;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Traits\QueryTrait;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts the items and collections to what the current user may read (public access when offline or
 * without journal, access by role in the journal of the token), then applies the query filters of the resource.
 */
class AppQueryItemCollectionExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{
    use QueryTrait;

    /** Journal settings restricting the editors (copy editors) to the papers they are assigned to */
    private const string SETTING_ENCAPSULATE_EDITORS = 'encapsulateEditors';
    private const string SETTING_ENCAPSULATE_COPY_EDITORS = 'encapsulateCopyEditors';

    /** Roles reading only the papers they are assigned to (unless they are editorial staff of every paper) */
    private const array ASSIGNED_ROLES = ['ROLE_EDITOR', 'ROLE_COPYEDITOR', 'ROLE_GUEST_EDITOR', 'ROLE_REVIEWER'];

    public function __construct(private Security $security)
    {
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
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

    public function applyToItem(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        array                       $identifiers,
        ?Operation                  $operation = null,
        array                       $context = []
    ): void
    {
        $context[AppConstants::IS_APP_ITEM] = true;

        $this->addWhere($queryBuilder, $resourceClass, $operation, $context);
    }

    /** @param array<string, mixed> $context */
    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation, array $context): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        if ($currentUser?->getCurrentJournalID()) {
            $this->privateAccessProcess($queryBuilder, $alias, $resourceClass, $currentUser, $operation, $context);
        } else {
            $this->publicAccessProcess($queryBuilder, $alias, $resourceClass, $context);
        }

        $this->applyResourceFilters($queryBuilder, $alias, $resourceClass, $context);
    }

    /**
     * Restrictions and filters of a resource, whatever the user.
     *
     * @param array<string, mixed> $context
     */
    private function applyResourceFilters(QueryBuilder $queryBuilder, string $alias, string $resourceClass, array $context): void
    {
        if ($resourceClass === Review::class) {
            $queryBuilder
                ->andWhere("$alias.rvid!= :portal")
                ->setParameter('portal', Review::PORTAL_ID)
                ->andWhere("$alias.status!= :status")
                ->setParameter('status', Review::STATUS_DISABLED);

            return;
        }

        if ($resourceClass === News::class && isset($context['filters'][AppConstants::YEAR_PARAM])) {
            $years = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
            $this->processOrExpression($queryBuilder, $alias, $years, $resourceClass);
        }

        if ($resourceClass === Page::class || $resourceClass === News::class) {
            // Use the indexed stored generated column instead of JSON_EXTRACT() to avoid
            // full-table scans that triggered sort_buffer_size overflow (MySQL error 1038).
            $queryBuilder->andWhere("$alias.is_public = :isPublic")->setParameter('isPublic', true);

            return;
        }

        if ($resourceClass === Paper::class) {
            $types = isset($context['filters']['type']) ? (array)$context['filters']['type'] : [];
            $this->andOrExp($queryBuilder, sprintf("JSON_EXTRACT(%s.type, '$.title')", $alias), $types);
        }
    }

    /**
     * Access of the anonymous users and of the users connected without journal: the published papers,
     * and the volumes (sections) holding them.
     *
     * @param array<string, mixed> $context
     */
    private function publicAccessProcess(QueryBuilder $queryBuilder, string $alias, string $resourceClass, array $context): void
    {
        if ($resourceClass === Paper::class) {
            /** @var HttpOperation|null $operation */
            $operation = $context['operation'] ?? null;
            $isPapersList = $operation?->getName() === Paper::COLLECTION_NAME && $this->isCollection($context);

            if ($isPapersList && $this->isOnlyAccepted($context)) {
                $this->andWhereAcceptedOnly($queryBuilder, $alias);
            } else {
                // an accepted paper can be read, but is only listed with only_accepted
                $this->andWherePublished($queryBuilder, "$alias.status", $resourceClass, $this->isItem($context));
                $this->andWherePublicationYears($queryBuilder, $alias, $context);
            }
        } elseif ($resourceClass === Volume::class || $resourceClass === Section::class) {
            $this->andWherePublished($queryBuilder, 'papers_a1.status', $resourceClass);
        } else {
            return;
        }

        $this->andWhereRequestedJournal($queryBuilder, $alias, $context);
    }

    /**
     * Access of the users connected to a journal (the journal of the token).
     *
     * @param array<string, mixed> $context
     */
    private function privateAccessProcess(
        QueryBuilder $queryBuilder,
        string       $alias,
        string       $resourceClass,
        User         $currentUser,
        ?Operation   $operation,
        array        $context
    ): void
    {
        if ($resourceClass === User::class) {
            // @see operation security allowed only for ROLE_SECRETARY
            $queryBuilder
                ->join(UserRoles::class, 'ur', 'ON', "$alias.uid = ur.uid")
                ->andWhere("ur.roleid!= :epiAdminRole")->setParameter('epiAdminRole', User::ROLE_ROOT)
                ->andWhere("$alias.uid!= :systemUid")->setParameter('systemUid', User::EPISCIENCES_UID)
                ->andWhere("ur.rvid= :userVid")->setParameter('userVid', $currentUser->getCurrentJournalID());
        } elseif ($resourceClass === Paper::class) {
            $this->privatePaperAccess($queryBuilder, $alias, $currentUser, $context);
        } elseif ($resourceClass === Volume::class || $resourceClass === Section::class) {
            if (!$this->security->isGranted('ROLE_EDITOR')) {
                $this->andWherePublished($queryBuilder, 'papers_a1.status', $resourceClass);
            } elseif ($this->isJournalListOperation($operation)) {
                // replaces the previous conditions: the editors read every volume (section) of their journal
                $queryBuilder
                    ->where("$alias.rvid= :rvId")
                    ->setParameter('rvId', $currentUser->getCurrentJournalID());
            }
        }
    }

    /**
     * Papers read by a connected user, according to the `scope` of the list (an item is always read with scope=all).
     * With scope=workspace, the papers are restricted to the journal of the token: a journal other than this one
     * returns no paper; with scope=all, it returns the public papers of this journal.
     *
     * @param array<string, mixed> $context
     */
    private function privatePaperAccess(QueryBuilder $queryBuilder, string $alias, User $currentUser, array $context): void
    {
        $isItem = $this->isItem($context);
        $isOnlyAccepted = $this->isOnlyAccepted($context);
        $scope = $isItem ? Paper::SCOPE_ALL : $this->paperScope($context);

        if ($scope === Paper::SCOPE_PUBLIC) { // the papers listed in offline mode
            $this->publicAccessProcess($queryBuilder, $alias, Paper::class, $context);

            return;
        }

        if ($isOnlyAccepted) {
            $this->andWhereAcceptedOnly($queryBuilder, $alias);
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

        $requestedRvId = $context['filters']['rvid'] ?? null;

        if ($requestedRvId) {
            $queryBuilder->andWhere("$alias.rvid = :requestedRvId")->setParameter('requestedRvId', $requestedRvId);
        }

        $this->andWherePublicationYears($queryBuilder, $alias, $context);
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
     * Papers of the journal of the token that the role of the connected user opens to them: every paper
     * for the secretaries (and the roles above) and the editors (copy editors) who are not encapsulated,
     * the assigned papers for the other editorial roles and the reviewers, and, for everyone, the papers
     * they submitted or co-authored (as isRestrictedAccess in the gpl).
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

        if (!array_any(self::ASSIGNED_ROLES, fn(string $role): bool => $this->security->isGranted($role))) {
            return $access->add($this->ownPaperCondition($queryBuilder, $alias, $currentUser));
        }

        // Parameters of the assignment subqueries
        $queryBuilder
            ->setParameter('type', UserAssignment::ITEM_PAPER)
            ->setParameter('to', $currentUser->getUid())
            ->setParameter('activeAssignment', UserAssignment::STATUS_ACTIVE)
            ->setParameter('managingRoles', [UserAssignment::ROLE_EDITOR, UserAssignment::ROLE_COPY_EDITOR])
            ->orderBy("$alias.when", \SortDirection::Descending);

        return $access->add($expr->orX(
            // their own papers, as for the other users (also sets :closedStatuses)
            $this->ownPaperCondition($queryBuilder, $alias, $currentUser),
            // a deleted or removed paper is only shown by its administration page, to its editors and copy editors
            $expr->andX(
                "$alias.status NOT IN (:closedStatuses)",
                $expr->in("$alias.docid", $this->currentAssignmentDql($queryBuilder, 'uAss'))
            ),
            $expr->in("$alias.docid", $this->currentAssignmentDql($queryBuilder, 'eAss', true)),
            $expr->in("$alias.paperid", $this->latestVersionsAssignedDql($queryBuilder))
        ));
    }

    /**
     * Papers of the author, whatever their other roles: submitted by them, or of which they are a
     * co-author, except the deleted or removed ones (isRestrictedAccess and paper/view in the gpl).
     */
    private function ownPaperCondition(QueryBuilder $queryBuilder, string $alias, User $currentUser): Andx
    {
        $expr = $queryBuilder->expr();

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

        return $expr->andX(
            $expr->orX(
                "$alias.user= :currentUser",
                $expr->exists($coAuthorAssignment->getDQL())
            ),
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
            // a temporary user has an id of another table than the uid of the users
            ->andWhere("$assignmentAlias.tmpUser = false")
            ->andWhere("$assignmentAlias.status = :activeAssignment")
            ->andWhere($expr->not($expr->exists($this->laterAssignmentDql($queryBuilder, $assignmentAlias))));

        if ($editorsOnly) {
            $assignment->andWhere("$assignmentAlias.roleid IN (:managingRoles)");
        }

        return $assignment->getDQL();
    }

    /**
     * DQL of the paperids of the latest versions the connected user (:to) is currently assigned to as editor
     * or copy editor, which open them the previous versions of these papers (Episciences_Paper::isAssignedEditorOrCopyEditor
     * in the gpl). Computed once from the assignments of the user rather than for every paper of the journal.
     */
    private function latestVersionsAssignedDql(QueryBuilder $queryBuilder): string
    {
        $expr = $queryBuilder->expr();

        $newerVersion = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select('newerVersion.docid')
            ->from(Paper::class, 'newerVersion')
            ->where('newerVersion.paperid = latestVersion.paperid')
            ->andWhere('newerVersion.when > latestVersion.when');

        return $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select('latestVersion.paperid')
            ->from(UserAssignment::class, 'vAss')
            ->join(Paper::class, 'latestVersion', 'WITH', 'latestVersion.docid = vAss.itemid')
            ->where('vAss.item = :type')
            ->andWhere('vAss.uid = :to')
            ->andWhere('vAss.status = :activeAssignment')
            ->andWhere('vAss.roleid IN (:managingRoles)')
            ->andWhere($expr->not($expr->exists($this->laterAssignmentDql($queryBuilder, 'vAss'))))
            ->andWhere($expr->not($expr->exists($newerVersion->getDQL())))
            ->getDQL();
    }

    /**
     * DQL of the assignments of the same user, paper and role made after the given one (the latest id
     * first for the same date, as the row kept by the gpl): when there is none, the given assignment is
     * the latest one.
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
            ->andWhere("$later.tmpUser = false")
            ->andWhere("$later.roleid = $assignmentAlias.roleid")
            ->andWhere("$later.when > $assignmentAlias.when OR ($later.when = $assignmentAlias.when AND $later.id > $assignmentAlias.id)")
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

    /**
     * Published papers (volumes, sections holding them), sorted by publication date for the papers;
     * the accepted papers too when $withAccepted.
     */
    private function andWherePublished(QueryBuilder $queryBuilder, string $statusField, string $resourceClass, bool $withAccepted = false): void
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $parameters = $queryBuilder->getParameters()->getValues();

        if ($parameters !== [] && $resourceClass === Paper::class) {
            // a paper is also found by its paperid: the first parameter is the requested docid
            $docId = $parameters[array_key_first($parameters)]->getValue();

            if ($docId) {
                $queryBuilder->orWhere("$alias.paperid = :paperId")->setParameter('paperId', $docId);
            }
        }

        if ($withAccepted) {
            $this->andOrExp($queryBuilder, $statusField, [...Paper::STATUS_ACCEPTED, Paper::STATUS_PUBLISHED]);

            return;
        }

        $queryBuilder->andWhere("$statusField= :published")->setParameter('published', Paper::STATUS_PUBLISHED);

        if ($resourceClass === Paper::class) {
            $queryBuilder->addOrderBy("$alias.publicationDate", \SortDirection::Descending);
        }
    }

    private function andWhereAcceptedOnly(QueryBuilder $queryBuilder, string $alias): void
    {
        $this->andOrExp($queryBuilder, "$alias.status", Paper::STATUS_ACCEPTED);
        $queryBuilder->addOrderBy("$alias.modificationDate", \SortDirection::Descending);
    }

    /**
     * `year` filter of the papers, on their publication date.
     *
     * @param array<string, mixed> $context
     */
    private function andWherePublicationYears(QueryBuilder $queryBuilder, string $alias, array $context): void
    {
        if (isset($context['filters'][AppConstants::YEAR_PARAM])) {
            $years = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
            $this->andOrExp($queryBuilder, "YEAR($alias.publicationDate)", $years);
        }
    }

    /**
     * `rvcode` filter, resolved into a journal id by applyToCollection().
     *
     * @param array<string, mixed> $context
     */
    private function andWhereRequestedJournal(QueryBuilder $queryBuilder, string $alias, array $context): void
    {
        $rvId = $context['filters']['rvid'] ?? null;

        if ($rvId) {
            $queryBuilder->andWhere("$alias.rvid = :rvId")->setParameter('rvId', $rvId);
        }
    }

    /**
     * Whether the operation lists the volumes (sections) of the journal, which the editors read whole.
     */
    private function isJournalListOperation(?Operation $operation): bool
    {
        if (!$operation instanceof HttpOperation) {
            return false;
        }

        $uriTemplate = (string)$operation->getUriTemplate();

        return str_starts_with($uriTemplate, Volume::DEFAULT_URI_TEMPLATE) || str_starts_with($uriTemplate, Section::DEFAULT_URI_TEMPLATE);
    }

    /** @param array<string, mixed> $context */
    private function isItem(array $context): bool
    {
        return filter_var($context[AppConstants::IS_APP_ITEM] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $context */
    private function isCollection(array $context): bool
    {
        return filter_var($context[AppConstants::IS_APP_COLLECTION] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $context */
    private function isOnlyAccepted(array $context): bool
    {
        return filter_var($context['filters']['only_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
