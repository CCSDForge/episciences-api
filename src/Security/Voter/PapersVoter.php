<?php

namespace App\Security\Voter;

use App\Entity\Papers;
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
            self::PAPERS_EDIT, self::PAPERS_VIEW, self::PAPERS_MANAGE,self::PAPERS_REVIEW, self::PAPERS_FOLLOW
            ]) && $subject instanceof Papers;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {


        if ($this->security->isGranted(User::ROLE_ROOT)) {
            return true;
        }


        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        // check conditions and return true to grant permission
        return match ($attribute) {
            self::PAPERS_EDIT => $this->canEdit($user, $subject),
            self::PAPERS_VIEW => $this->canView($user, $subject),
            self::PAPERS_MANAGE => $this->canManage($user, $subject),
            self::PAPERS_FOLLOW => $this->canFollow($user, $subject),
            self::PAPERS_REVIEW => $this->canReview($user, $subject),
            default => false,
        };

    }

    private function canView(User $user, Papers $paper): bool
    {
        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();


        $isEditor = in_array($subjectUser->getUid(), $paper->getEditors(), true);
        $isReviewer = in_array($subjectUser->getUid(), $paper->getReviewers(), true);
        $isCopyEditor = in_array($subjectUser->getUid(), $paper->getCopyEditors(), true);
        $isCoAuthor = in_array($subjectUser->getUid(), $paper->getCoAuthors(), true);
        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return
            $user->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
            (
                !$hasConflict &&
                (
                    $subjectUser->getUid() === $user->getUid() ||
                    $isCoAuthor ||
                    $isCopyEditor ||
                    $isReviewer ||
                    $isEditor
                )
            );
    }

    private function canEdit(User $user, Papers $paper): bool
    {

        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();

        $isEditor = in_array($subjectUser->getUid(), $paper->getEditors(), true);
        $isCopyEditor = in_array($subjectUser->getUid(), $paper->getCopyEditors(), true);
        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return

            (
                !$hasConflict &&
                (
                    $user->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
                    $subjectUser->getUid() === $user->getUid() ||
                    $isCopyEditor ||
                    $isEditor
                )
            );

    }


    private function canReview(User $user, Papers $paper): bool
    {
        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();

        $isEditor = in_array($subjectUser->getUid(), $paper->getEditors(), true);
        $isReviewer = in_array($subjectUser->getUid(), $paper->getReviewers(), true);

        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return

            (
                !$hasConflict &&
                $subjectUser->getUid() !== $user->getUid()
                &&
                (
                    $user->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
                    $isEditor ||
                    $isReviewer

                )
            );
    }

    private function canFollow(User $user, Papers $paper): bool
    {
        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();
        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return $hasConflict && $user->hasRole(User::ROLE_EDITOR, $paper->getRvid());

    }

    private function canManage(User $user, Papers $paper): bool
    {

        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();

        $isEditor = in_array($subjectUser->getUid(), $paper->getEditors(), true);

        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return true;

//        return
//
//            !$hasConflict && $subjectUser->getUid() !== $user->getUid() &&
//            (
//                $user->hasRole(User::ROLE_SECRETARY, $paper->getRvid()) ||
//                $isEditor
//            );

    }

}
