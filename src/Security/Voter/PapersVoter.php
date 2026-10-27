<?php

namespace App\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\User;
use App\Repository\PaperConflictsRepository;
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

    public function __construct(
        private readonly PaperConflictsRepository $paperConflictsRepository
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

        $isEditor = array_key_exists($authenticatedUser->getUid(), $paper->getEditors());
        $isReviewer = array_key_exists($authenticatedUser->getUid(), $paper->getReviewers());
        $isCopyEditor = array_key_exists($authenticatedUser->getUid(), $paper->getCopyEditors());
        $isCoAuthor = array_key_exists($authenticatedUser->getUid(), $paper->getCoAuthors());

        return
            !$this->hasConflict($authenticatedUser, $paper) &&
            (
                $this->isSecretary($authenticatedUser, $paper) ||
                (
                    $paper->getUid() === $authenticatedUser->getUid() ||
                    $isCoAuthor ||
                    $isCopyEditor ||
                    $isReviewer ||
                    $isEditor
                )
            );
    }

    private function canEdit(User $authenticatedUser, Paper $paper): bool
    {
        $isSecretary = $this->isSecretary($authenticatedUser, $paper);
        $isEditor = array_key_exists($authenticatedUser->getUid(), $paper->getEditors());
        $isCopyEditor = array_key_exists($authenticatedUser->getUid(), $paper->getCopyEditors());

        return

            (
                !$this->hasConflict($authenticatedUser, $paper) &&
                (
                    $isSecretary ||
                    $paper->getUid() === $authenticatedUser->getUid() ||
                    $isCopyEditor ||
                    $isEditor
                )
            );

    }


    private function canReview(User $authenticatedUser, Paper $paper): bool
    {
        $isEditor = array_key_exists($authenticatedUser->getUid(), $paper->getEditors());
        $isReviewer = array_key_exists($authenticatedUser->getUid(), $paper->getReviewers());

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
