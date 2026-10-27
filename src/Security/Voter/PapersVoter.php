<?php

namespace App\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Repository\PaperConflictsRepository;
use App\Repository\PapersRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PapersVoter extends Voter
{
    public const PAPERS_EDIT = 'papers_edit';
    public const PAPERS_VIEW = 'papers_view';

    public const PAPERS_MANAGE = 'papers_manage';
    public const PAPERS_REVIEW = 'papers_review';

    public const PAPERS_FOLLOW = 'papers_follow';

    /** Journal settings restricting the editors (copy editors) to the papers they are assigned to */
    private const string SETTING_ENCAPSULATE_EDITORS = 'encapsulateEditors';
    private const string SETTING_ENCAPSULATE_COPY_EDITORS = 'encapsulateCopyEditors';

    /** @var array<int, Paper|null> latest version of the papers already voted on, by docid */
    private array $latestVersions = [];

    public function __construct(
        private readonly PaperConflictsRepository $paperConflictsRepository,
        private readonly PapersRepository $papersRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::PAPERS_EDIT, self::PAPERS_VIEW, self::PAPERS_MANAGE, self::PAPERS_REVIEW, self::PAPERS_FOLLOW
            ]) && $subject instanceof Paper;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {

        $authenticatedUser = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$authenticatedUser instanceof User) {
            return false;
        }

        // A paper deleted by its author or removed by the journal: its page is closed to everyone, its
        // administration page still shows it to the users who manage it, and nobody changes it anymore
        // (paper/view, administratepaper/view and findManagementRefusal in the gpl)
        if (in_array($subject->getStatus(), [Paper::STATUS_DELETED, Paper::STATUS_REMOVED], true)) {
            if (in_array($attribute, [self::PAPERS_EDIT, self::PAPERS_REVIEW], true)) {
                return false;
            }

            if ($attribute === self::PAPERS_VIEW) {
                $attribute = self::PAPERS_MANAGE;
            }
        }

        // As on the administration page of the gpl (findManagementRefusal), nobody manages or reviews
        // their own paper, whatever their roles (root and administrators included)
        if (
            $subject->getUid() === $authenticatedUser->getUid() &&
            in_array($attribute, [self::PAPERS_MANAGE, self::PAPERS_REVIEW], true)
        ) {
            return false;
        }

        // Root of the journal of the paper: every role, never in conflict of interest
        // (see DefaultController::isConflictDetected in the gpl)
        if ($this->isRoot($authenticatedUser, $subject)) {
            return true;
        }

        if ($this->hasOnlyAdministratorRole($authenticatedUser, $subject)) {
            return !$this->hasConflict($authenticatedUser, $subject);
        }

        // check conditions and return true to grant permission
        return match ($attribute) {
            self::PAPERS_EDIT => $this->canEdit($authenticatedUser, $subject),
            self::PAPERS_VIEW => $this->canView($authenticatedUser, $subject),
            self::PAPERS_MANAGE => $this->canManage($authenticatedUser, $subject),
            self::PAPERS_FOLLOW => $this->canFollow($authenticatedUser, $subject),
            self::PAPERS_REVIEW => $this->canReview($authenticatedUser, $subject),
            default => false,
        };

    }

    private function canView(User $authenticatedUser, Paper $paper): bool
    {

        $isEditor = $this->isAssignedEditor($authenticatedUser, $paper);
        $isReviewer = $this->isAssigned($authenticatedUser, $paper->getReviewers());
        $isCopyEditor = $this->isAssignedCopyEditor($authenticatedUser, $paper);

        // As on the member page of the paper in the gpl, its author always sees its details; its
        // co-authors only open the page (isAllowedToSeeNoPublicDetails: isOwner, not isCoauthor)
        if ($paper->getUid() === $authenticatedUser->getUid()) {
            return true;
        }

        return
            !$this->hasConflict($authenticatedUser, $paper) &&
            (
                $this->isEditorialStaffOfEveryPaper($authenticatedUser, $paper) ||
                (
                    $isCopyEditor ||
                    $isReviewer ||
                    $isEditor
                )
            );
    }

    private function canEdit(User $authenticatedUser, Paper $paper): bool
    {
        // Editors and copy editors only handle the papers they are assigned to, whatever the
        // encapsulation settings of the journal: below the secretaries, no role covers every paper.
        $isEditor = $this->isAssignedEditor($authenticatedUser, $paper);
        $isCopyEditor = $this->isAssignedCopyEditor($authenticatedUser, $paper);

        return

            (
                !$this->hasConflict($authenticatedUser, $paper) &&
                (
                    $this->isSecretary($authenticatedUser, $paper) ||
                    $paper->getUid() === $authenticatedUser->getUid() ||
                    $isCopyEditor ||
                    $isEditor
                )
            );

    }


    private function canReview(User $authenticatedUser, Paper $paper): bool
    {
        $isEditor = $this->isAssignedEditor($authenticatedUser, $paper);
        $isReviewer = $this->isAssigned($authenticatedUser, $paper->getReviewers());

        return

            (
                !$this->hasConflict($authenticatedUser, $paper) &&
                $paper->getUid() !== $authenticatedUser->getUid()
                &&
                (
                    $this->isSecretary($authenticatedUser, $paper) ||
                    $isEditor ||
                    $isReviewer

                )
            );
    }

    private function canFollow(User $authenticatedUser, Paper $paper): bool
    {
        return !$this->hasConflict($authenticatedUser, $paper) && $this->isEditor($authenticatedUser, $paper);
    }

    private function canManage(User $authenticatedUser, Paper $paper): bool
    {
        return $paper->getUid() !== $authenticatedUser->getUid() && $this->canEdit($authenticatedUser, $paper);
    }

    /**
     * Editor currently assigned to the paper, or to its latest version (Episciences_Paper::
     * isAssignedEditorOrCopyEditor in the gpl): the editor of the latest version handles the previous ones.
     */
    private function isAssignedEditor(User $authenticatedUser, Paper $paper): bool
    {
        if ($this->isAssigned($authenticatedUser, $paper->getEditors())) {
            return true;
        }

        $latestVersion = $this->latestVersion($paper);

        return $latestVersion !== null && $this->isAssigned($authenticatedUser, $latestVersion->getEditors());
    }

    /**
     * Copy editor currently assigned to the paper, or to its latest version (see isAssignedEditor()).
     */
    private function isAssignedCopyEditor(User $authenticatedUser, Paper $paper): bool
    {
        if ($this->isAssigned($authenticatedUser, $paper->getCopyEditors())) {
            return true;
        }

        $latestVersion = $this->latestVersion($paper);

        return $latestVersion !== null && $this->isAssigned($authenticatedUser, $latestVersion->getCopyEditors());
    }

    private function latestVersion(Paper $paper): ?Paper
    {
        $docId = $paper->getDocid();

        if ($docId === null) {
            return $this->papersRepository->findLatestVersion($paper);
        }

        if (!array_key_exists($docId, $this->latestVersions)) {
            $this->latestVersions[$docId] = $this->papersRepository->findLatestVersion($paper);
        }

        return $this->latestVersions[$docId];
    }

    /**
     * Whether the user is currently assigned to the paper in one of its roles (editor, copy editor,
     * reviewer): their latest assignment is active, as Episciences_PapersManager::getEditors() and
     * getReviewers() in the gpl. An editor unassigned or a reviewer who declined is no longer assigned.
     *
     * @param array<int, mixed> $assignmentsByUid assignments of the paper in this role, grouped by uid
     */
    private function isAssigned(User $authenticatedUser, array $assignmentsByUid): bool
    {
        $latest = null;

        foreach ((array)($assignmentsByUid[$authenticatedUser->getUid()] ?? []) as $assignment) {
            if ($assignment instanceof UserAssignment && ($latest === null || $assignment->getWhen() > $latest->getWhen())) {
                $latest = $assignment;
            }
        }

        return $latest?->getStatus() === UserAssignment::STATUS_ACTIVE;
    }

    /**
     * Roles held by the user in the journal of the paper (role ids, e.g. "secretary"). The roles of a
     * token are those of its journal (rvId claim), whatever the journal asked to User::getRoles():
     * the user has none in another journal.
     *
     * @return list<string>
     */
    private function journalRoles(User $authenticatedUser, Paper $paper): array
    {
        $rvId = $paper->getRvid();
        $currentJournalId = $authenticatedUser->getCurrentJournalID();

        if ($rvId === null || ($currentJournalId !== null && $currentJournalId !== $rvId)) {
            return [];
        }

        return array_map(
            static fn(string $role): string => strtolower(str_starts_with($role, 'ROLE_') ? substr($role, 5) : $role),
            array_values($authenticatedUser->getRoles($rvId))
        );
    }

    /**
     * Whether the user holds exactly this role in the journal of the paper (Episciences_Auth::is in the gpl).
     */
    private function is(User $authenticatedUser, Paper $paper, string $roleId): bool
    {
        return in_array($roleId, $this->journalRoles($authenticatedUser, $paper), true);
    }

    // The roles below follow Episciences_Auth in the gpl, in the journal of the paper:
    // root > chief editor > administrator > secretary, editor, guest editor.

    private function isRoot(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_ROOT);
    }

    private function isChiefEditor(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_EDITOR_IN_CHIEF) || $this->isRoot($authenticatedUser, $paper);
    }

    private function isAdministrator(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_ADMINISTRATOR) || $this->isChiefEditor($authenticatedUser, $paper);
    }

    private function isSecretary(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_SECRETARY) || $this->isAdministrator($authenticatedUser, $paper);
    }

    private function isEditor(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_EDITOR) || $this->isAdministrator($authenticatedUser, $paper);
    }

    private function isCopyEditor(User $authenticatedUser, Paper $paper): bool
    {
        return $this->is($authenticatedUser, $paper, User::ROLE_COPY_EDITOR);
    }

    /**
     * Editorial staff of every paper of the journal for viewing the details of a paper, assigned or
     * not (DefaultController::isEditorialStaffOfEveryPaper in the gpl): the secretaries, and the
     * editors (copy editors) when the journal explicitly sets their encapsulation to "0" (a setting
     * never saved counts as restrictive). Managing a paper still requires being assigned to it.
     */
    private function isEditorialStaffOfEveryPaper(User $authenticatedUser, Paper $paper): bool
    {
        return
            $this->isSecretary($authenticatedUser, $paper) ||
            ($this->journalSetting($paper, self::SETTING_ENCAPSULATE_EDITORS) === '0' && $this->isEditor($authenticatedUser, $paper)) ||
            ($this->journalSetting($paper, self::SETTING_ENCAPSULATE_COPY_EDITORS) === '0' && $this->isCopyEditor($authenticatedUser, $paper));
    }

    private function journalSetting(Paper $paper, string $setting): ?string
    {
        return $paper->getReview()?->getSetting($setting);
    }

    /**
     * Administrator of the journal of the paper without any other editorial role in it
     * (Episciences_Auth::hasOnlyAdministratorRole in the gpl).
     */
    private function hasOnlyAdministratorRole(User $authenticatedUser, Paper $paper): bool
    {
        return
            $this->is($authenticatedUser, $paper, User::ROLE_ADMINISTRATOR) &&
            !$this->is($authenticatedUser, $paper, User::ROLE_EDITOR_IN_CHIEF) &&
            !$this->is($authenticatedUser, $paper, User::ROLE_SECRETARY) &&
            !$this->is($authenticatedUser, $paper, User::ROLE_EDITOR) &&
            !$this->is($authenticatedUser, $paper, User::ROLE_GUEST_EDITOR) &&
            !$this->is($authenticatedUser, $paper, User::ROLE_COPY_EDITOR);
    }

    /**
     * Conflict of interest of the user on the paper, as DefaultController::isConflictDetected in the
     * gpl, when the conflicts of interest are enabled for the journal: the users allowed to declare
     * one are in conflict until they answer "no" (no answer counts as "later"), an administrator
     * only (who cannot declare one) when they answered "yes", the other users and root never.
     */
    private function hasConflict(User $authenticatedUser, Paper $currentPaper): bool
    {
        if (!$this->isCoiEnabled($currentPaper) || $this->isRoot($authenticatedUser, $currentPaper)) {
            return false;
        }

        $answer = $this->conflictAnswer($authenticatedUser, $currentPaper);

        if ($this->hasOnlyAdministratorRole($authenticatedUser, $currentPaper)) {
            return $answer === PaperConflicts::AVAILABLE_ANSWER['yes'];
        }

        return
            $this->isAllowedToDeclareConflict($authenticatedUser, $currentPaper) &&
            in_array($answer, [PaperConflicts::AVAILABLE_ANSWER['yes'], PaperConflicts::AVAILABLE_ANSWER['later']], true);
    }

    /**
     * Answer of the user to the conflict of interest question of the paper, "later" when they have
     * not answered (Episciences_Paper::checkConflictResponse in the gpl).
     */
    private function conflictAnswer(User $authenticatedUser, Paper $currentPaper): string
    {
        if ($currentPaper->getPaperid() !== null && !$currentPaper->conflictsLoaded()) {
            $currentPaper->setConflicts($this->paperConflictsRepository->findByPaperId($currentPaper->getPaperid()));
        }

        foreach (PaperConflicts::AVAILABLE_ANSWER as $answer) {
            $answerGroup = $currentPaper->getConflicts()->get($answer);

            if (is_array($answerGroup) && array_key_exists($authenticatedUser->getUid(), $answerGroup)) {
                return $answer;
            }
        }

        return PaperConflicts::AVAILABLE_ANSWER['later'];
    }

    /**
     * Editorial roles allowed to declare a conflict of interest, without inheritance
     * (Episciences_Auth::isAllowedToDeclareConflict in the gpl).
     */
    private function isAllowedToDeclareConflict(User $authenticatedUser, Paper $paper): bool
    {
        return
            $this->is($authenticatedUser, $paper, User::ROLE_COPY_EDITOR) ||
            $this->is($authenticatedUser, $paper, User::ROLE_GUEST_EDITOR) ||
            $this->is($authenticatedUser, $paper, User::ROLE_EDITOR) ||
            $this->is($authenticatedUser, $paper, User::ROLE_SECRETARY) ||
            $this->is($authenticatedUser, $paper, User::ROLE_EDITOR_IN_CHIEF);
    }

    private function isCoiEnabled(Paper $currentPaper): bool
    {
        return (bool)$currentPaper->getReview()?->getSetting('isCoiEnabled');
    }

}
