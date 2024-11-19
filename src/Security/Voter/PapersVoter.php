<?php

namespace App\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PapersVoter extends Voter
{
    public const PAPERS_EDIT = 'papers_edit';
    public const PAPERS_VIEW = 'papers_view';

    public const PAPERS_MANAGE = 'papers_manage';
    public const PAPERS_REVIEW = 'papers_review';

    public const PAPERS_FOLLOW = 'papers_follow';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::PAPERS_EDIT, self::PAPERS_VIEW, self::PAPERS_MANAGE, self::PAPERS_REVIEW, self::PAPERS_FOLLOW
            ]) && $subject instanceof Paper;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
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

        $isEditor = in_array($authenticatedUser->getUid(), $paper->getEditors(), true);
        $isReviewer = in_array($authenticatedUser->getUid(), $paper->getReviewers(), true);
        $isCopyEditor = in_array($authenticatedUser->getUid(), $paper->getCopyEditors(), true);
        $isCoAuthor = in_array($authenticatedUser->getUid(), $paper->getCoAuthors(), true);

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
        $isEditor = in_array($authenticatedUser->getUid(), $paper->getEditors(), true);
        $isCopyEditor = in_array($authenticatedUser->getUid(), $paper->getCopyEditors(), true);

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
        $isEditor = in_array($authenticatedUser->getUid(), $paper->getEditors(), true);
        $isReviewer = in_array($authenticatedUser->getUid(), $paper->getReviewers(), true);

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

        return !array_key_exists($authenticatedUser->getUid(), $currentPaper->getConflicts()->get(PaperConflicts::AVAILABLE_ANSWER['no']));

    }

}
