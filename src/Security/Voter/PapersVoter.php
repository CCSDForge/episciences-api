<?php

namespace App\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
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

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::PAPERS_EDIT, self::PAPERS_VIEW, self::PAPERS_MANAGE, self::PAPERS_REVIEW, self::PAPERS_FOLLOW
            ]) && $subject instanceof Paper;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {

        if (
            $this->security->isGranted('ROLE_EPIADMIN')
        ) {
            return true;
        }

        $authenticatedUser = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$authenticatedUser instanceof User) {
            return false;
        }

        if (
            $this->security->isGranted('ROLE_ADMINISTRATOR') &&
            !(
                $authenticatedUser->hasRole(User::ROLE_SECRETARY, $subject->getRvid()) ||
                $authenticatedUser->hasRole(User::ROLE_EDITOR_IN_CHIEF, $subject->getRvid()) ||
                $authenticatedUser->hasRole(User::ROLE_GUEST_EDITOR, $subject->getRvid()) ||
                $authenticatedUser->hasRole(User::ROLE_EDITOR, $subject->getRvid()) ||
                $authenticatedUser->hasRole(User::ROLE_COPY_EDITOR, $subject->getRvid())
            )
        ) {
            return true;
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
            $authenticatedUser->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
            (
                !$this->hasConflict($authenticatedUser, $paper) &&
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
        $isSecretary = $this->security->isGranted('ROLE_SECRETARY');//according to role hierarchy: not use $authenticatedUser->hasRole()
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
                    $authenticatedUser->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
                    $isEditor ||
                    $isReviewer

                )
            );
    }

    private function canFollow(User $authenticatedUser, Paper $paper): bool
    {
        return $this->hasConflict($authenticatedUser, $paper) && $authenticatedUser->hasRole(User::ROLE_EDITOR, $paper->getRvid());
    }

    private function canManage(User $authenticatedUser, Paper $paper): bool
    {
        return $paper->getUid() !== $authenticatedUser->getUid() && $this->canEdit($authenticatedUser, $paper);
    }

    private function hasConflict(User $authenticatedUser, Paper $currentPaper): bool
    {
        $isCoiEnabled = $currentPaper->getReview()?->getSetting('isCoiEnabled');

        if(!$isCoiEnabled){
            return false;
        }

        $noConflictGroup = $currentPaper->getConflicts()->get(PaperConflicts::AVAILABLE_ANSWER['no']);

        return !is_array($noConflictGroup) || !array_key_exists($authenticatedUser->getUid(), $noConflictGroup);

    }

}
