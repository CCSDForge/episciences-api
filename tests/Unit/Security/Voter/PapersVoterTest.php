<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\User;
use App\Security\Voter\PapersVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PapersVoterTest extends TestCase
{
    private Security&MockObject $security;
    private PapersVoter $voter;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter    = new PapersVoter($this->security);
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(int $uid, array $roles = []): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('hasRole')->willReturnCallback(
            static fn($role) => in_array('ROLE_' . strtoupper($role), $roles, true)
        );
        return $user;
    }

    /**
     * @param array<int, mixed> $editors     uid => anything
     * @param array<int, mixed> $reviewers   uid => anything
     * @param array<int, mixed> $copyEditors uid => anything
     * @param array<int, mixed> $coAuthors   uid => anything
     * @param bool              $coiEnabled
     * @param ArrayCollection   $conflicts
     */
    private function makePaper(
        int $uid,
        int $rvid = 1,
        array $editors = [],
        array $reviewers = [],
        array $copyEditors = [],
        array $coAuthors = [],
        bool $coiEnabled = false,
        ?ArrayCollection $conflicts = null
    ): Paper&MockObject {
        $review = $this->createMock(Review::class);
        $review->method('getSetting')
            ->with('isCoiEnabled')
            ->willReturn($coiEnabled ? '1' : null);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn($uid);
        $paper->method('getRvid')->willReturn($rvid);
        $paper->method('getEditors')->willReturn($editors);
        $paper->method('getReviewers')->willReturn($reviewers);
        $paper->method('getCopyEditors')->willReturn($copyEditors);
        $paper->method('getCoAuthors')->willReturn($coAuthors);
        $paper->method('getReview')->willReturn($review);
        $paper->method('getConflicts')->willReturn($conflicts ?? new ArrayCollection());
        return $paper;
    }

    private function makeToken(User&MockObject $user): TokenInterface&MockObject
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    // ------------------------------------------------------------------ supports()

    public function testSupportsReturnsTrueForValidAttributeAndPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(1);
        $paper = $this->makePaper(1);
        $token = $this->makeToken($user);
        // author can view → GRANT (1)
        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(1);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);
        $this->assertSame(0, $this->voter->vote($token, $paper, ['unknown_attr']));
    }

    public function testAbstainsForNonPaperSubject(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(1);
        $token = $this->makeToken($user);
        $this->assertSame(0, $this->voter->vote($token, new \stdClass(), [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ ROLE_EPIADMIN

    public function testEpiAdminIsAlwaysGranted(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn($role) => $role === 'ROLE_EPIADMIN'
        );
        $user  = $this->makeUser(99);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    // ------------------------------------------------------------------ anonymous user

    public function testAnonymousUserIsDenied(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $paper = $this->makePaper(1);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ ROLE_ADMINISTRATOR

    public function testAdminWithoutSpecificRolesIsGranted(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn($role) => $role === 'ROLE_ADMINISTRATOR'
        );
        $user = $this->makeUser(42);
        $user->method('hasRole')->willReturn(false);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1, 1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    /** Admin who also has a specific role falls through to attribute-level checks. */
    public function testAdminWithSecretaryRoleFallsThroughToAttributeChecks(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn($role) => in_array($role, ['ROLE_ADMINISTRATOR', 'ROLE_SECRETARY'], true)
        );
        $user = $this->makeUser(42);
        $user->method('hasRole')->willReturnCallback(
            static fn($role) => $role === User::ROLE_SECRETARY
        );
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1, 1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    // ------------------------------------------------------------------ canView()

    public function testAuthorCanViewOwnPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testCoAuthorCanViewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(20);
        $paper = $this->makePaper(10, coAuthors: [20 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testEditorCanViewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(30);
        $paper = $this->makePaper(10, editors: [30 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testReviewerCanViewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(31);
        $paper = $this->makePaper(10, reviewers: [31 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testCopyEditorCanViewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(32);
        $paper = $this->makePaper(10, copyEditors: [32 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testSecretaryCanViewAnyPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(99, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testUnrelatedUserCannotViewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(99);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ canEdit()

    public function testSecretaryCanEdit(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn($role) => $role === 'ROLE_SECRETARY'
        );
        $user  = $this->makeUser(50);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testAuthorCanEditOwnPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testCopyEditorCanEdit(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(60);
        $paper = $this->makePaper(10, copyEditors: [60 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testPaperEditorCanEdit(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(61);
        $paper = $this->makePaper(10, editors: [61 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    // ------------------------------------------------------------------ canManage()

    public function testAuthorCannotManageOwnPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testSecretaryCanManagePaperByOtherAuthor(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn($role) => $role === 'ROLE_SECRETARY'
        );
        $user  = $this->makeUser(99);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ canReview()

    public function testAuthorCannotReviewOwnPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(10, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    public function testReviewerCanReviewPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(40);
        $paper = $this->makePaper(10, reviewers: [40 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    public function testSecretaryCanReviewOtherAuthorPaper(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(99, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    // ------------------------------------------------------------------ canFollow()

    public function testEditorWithConflictCanFollow(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // COI enabled, user 70 NOT in 'no' group → hasConflict = true → canFollow = true (editor with conflict)
        $conflicts = new ArrayCollection(['no' => [99 => new PaperConflicts()]]);
        $paper     = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_FOLLOW]));
    }

    public function testEditorWithoutConflictCannotFollow(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);
        // COI disabled → no conflict → canFollow returns false
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_FOLLOW]));
    }

    // ------------------------------------------------------------------ hasConflict() — null-safety regression

    /**
     * Regression: array_key_exists on null caused TypeError when no 'no' answer group existed.
     */
    public function testHasConflictDoesNotThrowWhenNoNoGroup(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(70);
        $token = $this->makeToken($user);

        // COI enabled, conflicts collection has NO 'no' key → get('no') returns null
        $paper = $this->makePaper(10, coiEnabled: true, conflicts: new ArrayCollection());
        $user->method('hasRole')->willReturn(false);

        // Must NOT throw TypeError. User not in 'no' group → has conflict → canView false
        $result = $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]);
        $this->assertSame(-1, $result);
    }

    public function testNoConflictWhenCoiDisabled(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10); // COI disabled by default

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testUserInNoGroupHasNoConflict(): void
    {
        $this->security->method('isGranted')->willReturn(false);
        $user  = $this->makeUser(70);
        $token = $this->makeToken($user);

        // uid 70 IS in the 'no' group → no conflict → can view if co-author
        $conflicts = new ArrayCollection(['no' => [70 => new PaperConflicts()]]);
        $paper     = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts, coAuthors: [70 => []]);
        $user->method('hasRole')->willReturn(false);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }
}
