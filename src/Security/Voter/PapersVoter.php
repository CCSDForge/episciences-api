<?php

namespace App\Security\Voter;

use App\Entity\Paper;
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
            ]) && $subject instanceof Paper;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {

        if ($this->security->isGranted('ROLE_EPIADMIN')) {
            return true;
        }

        $authenticatedUser = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$authenticatedUser instanceof User) {
            return false;
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

    private function canView(User $user, Paper $paper): bool
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

    private function canEdit(User $authenticatedUser, Paper $paper): bool
    {

        /** @var User $author */
        $author = $paper->getUser();

        $isSecretary = $this->security->isGranted('ROLE_SECRETARY');//according to role hierarchy: not use $authenticatedUser->hasRole()
        $isEditor = in_array($authenticatedUser->getUid(), $paper->getEditors(), true);
        $isCopyEditor = in_array($authenticatedUser->getUid(), $paper->getCopyEditors(), true);
        $hasConflict = $paper->getConflicts()->containsKey($authenticatedUser->getUid());

        return

            (
                !$hasConflict &&
                (
                    $isSecretary ||
                    $author->getUid() === $authenticatedUser->getUid() ||
                    $isCopyEditor ||
                    $isEditor
                )
            );

    }


    private function canReview(User $authenticatedUser, Paper $paper): bool
    {
        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();


        $isEditor = in_array($subjectUser->getUid(), $paper->getEditors(), true);
        $isReviewer = in_array($subjectUser->getUid(), $paper->getReviewers(), true);

        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return

            (
                !$hasConflict &&
                $subjectUser->getUid() !== $authenticatedUser->getUid()
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
        /** @var User $subjectUser */
        $subjectUser = $paper->getUser();
        $hasConflict = $paper->getConflicts()->containsKey($subjectUser->getUid());

        return $hasConflict && $authenticatedUser->hasRole(User::ROLE_EDITOR, $paper->getRvid());

    }

    private function canManage(User $authenticatedUser, Paper $paper): bool
    {
        /** @var User $author */
        $author = $paper->getUser();

        return $author->getUid() !== $authenticatedUser->getUid() && $this->canEdit($authenticatedUser, $paper);
    }

}
