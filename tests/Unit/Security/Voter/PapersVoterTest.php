<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\User;
use App\Security\Voter\PapersVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PapersVoterTest extends TestCase
{
    private function makeVoter(array $grantedRoles = []): PapersVoter
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => in_array($role, $grantedRoles, true)
        );
        return new PapersVoter($security);
    }

    private function makeToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    private function makeUser(int $uid): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($uid);
        return $user;
    }

    private function makePaper(int $uid, int $rvid = 1): Paper
    {
        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn($uid);
        $paper->method('getRvid')->willReturn($rvid);
        $paper->method('getEditors')->willReturn([]);
        $paper->method('getReviewers')->willReturn([]);
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        // COI disabled by default
        $paper->method('getReview')->willReturn(null);
        return $paper;
    }

    // ── supports ─────────────────────────────────────────────────────────────

    public function testSupports(): void
    {
        $voter = $this->makeVoter();
        $paper = $this->makePaper(1);
        $token = $this->makeToken($this->makeUser(1));

        // valid attribute + Paper → grants/denies (not abstains)
        $result = $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]);
        $this->assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);

        // unknown attribute → abstains
        $result = $voter->vote($token, $paper, ['unknown_attribute']);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);

        // valid attribute + wrong subject type → abstains
        $result = $voter->vote($token, new \stdClass(), [PapersVoter::PAPERS_VIEW]);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ── ROLE_EPIADMIN ─────────────────────────────────────────────────────────

    public function testEpiAdminAlwaysGranted(): void
    {
        $voter = $this->makeVoter(['ROLE_EPIADMIN']);
        $paper = $this->makePaper(99);
        $token = $this->makeToken($this->makeUser(1));

        foreach ([
            PapersVoter::PAPERS_VIEW,
            PapersVoter::PAPERS_EDIT,
            PapersVoter::PAPERS_MANAGE,
            PapersVoter::PAPERS_REVIEW,
            PapersVoter::PAPERS_FOLLOW,
        ] as $attribute) {
            $this->assertSame(
                VoterInterface::ACCESS_GRANTED,
                $voter->vote($token, $paper, [$attribute]),
                "ROLE_EPIADMIN should grant $attribute"
            );
        }
    }

    // ── anonymous user ────────────────────────────────────────────────────────

    public function testAnonymousUserDenied(): void
    {
        $voter = $this->makeVoter();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null); // anonymous

        $paper = $this->makePaper(1);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW])
        );
    }

    // ── ROLE_ADMINISTRATOR without specific roles → always granted ────────────

    public function testAdministratorWithoutSpecificRoleGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_ADMINISTRATOR'
        );
        $voter = new PapersVoter($security);

        $user = $this->makeUser(5);
        // No editor/secretary/etc roles
        $user->method('hasRole')->willReturn(false);

        $paper = $this->makePaper(99, 1);
        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW])
        );
    }

    // ── canView ───────────────────────────────────────────────────────────────

    public function testCanView_AuthorCanView(): void
    {
        $voter = $this->makeVoter(); // no special roles
        $user = $this->makeUser(10);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->makePaper(10); // same uid as user
        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW])
        );
    }

    public function testCanView_EditorCanView(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(20);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(99); // different owner
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getEditors')->willReturn([20]); // user is editor
        $paper->method('getReviewers')->willReturn([]);
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        $paper->method('getReview')->willReturn(null);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW])
        );
    }

    public function testCanView_CoiBlocksView(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(10);
        $user->method('hasRole')->willReturn(false);

        $review = $this->createMock(Review::class);
        $review->method('getSetting')->with('isCoiEnabled')->willReturn('1');

        // User (uid=10) is NOT in the "no conflict" list
        $conflictsCollection = new ArrayCollection(['no' => [999 => new \stdClass()]]);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(10); // owner
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getEditors')->willReturn([]);
        $paper->method('getReviewers')->willReturn([]);
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        $paper->method('getReview')->willReturn($review);
        $paper->method('getConflicts')->willReturn($conflictsCollection);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW])
        );
    }

    // ── canEdit ───────────────────────────────────────────────────────────────

    public function testCanEdit_SecretaryCanEdit(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_SECRETARY'
        );
        $voter = new PapersVoter($security);

        $user = $this->makeUser(5);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->makePaper(99); // different owner
        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT])
        );
    }

    public function testCanEdit_CoiBlocksEdit(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(10);
        $user->method('hasRole')->willReturn(false);

        $review = $this->createMock(Review::class);
        $review->method('getSetting')->with('isCoiEnabled')->willReturn('1');

        $conflictsCollection = new ArrayCollection(['no' => []]);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(10);
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getEditors')->willReturn([]);
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getReviewers')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        $paper->method('getReview')->willReturn($review);
        $paper->method('getConflicts')->willReturn($conflictsCollection);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT])
        );
    }

    // ── canReview ─────────────────────────────────────────────────────────────

    public function testCanReview_ReviewerCanReview(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(30);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(99); // different owner
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getEditors')->willReturn([]);
        $paper->method('getReviewers')->willReturn([30]); // user is reviewer
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        $paper->method('getReview')->willReturn(null);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW])
        );
    }

    public function testCanReview_AuthorCannotReviewOwnPaper(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(10);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(10); // same as user → owner
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getEditors')->willReturn([]);
        $paper->method('getReviewers')->willReturn([10]); // even if listed
        $paper->method('getCopyEditors')->willReturn([]);
        $paper->method('getCoAuthors')->willReturn([]);
        $paper->method('getReview')->willReturn(null);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW])
        );
    }

    // ── canFollow ─────────────────────────────────────────────────────────────

    public function testCanFollow_ConflictWithEditorRoleGranted(): void
    {
        $voter = $this->makeVoter();
        $user = $this->makeUser(10);
        // hasRole(ROLE_EDITOR, rvid) must return true
        $user->method('hasRole')->willReturnCallback(
            static fn(string $role) => $role === User::ROLE_EDITOR
        );

        $review = $this->createMock(Review::class);
        $review->method('getSetting')->with('isCoiEnabled')->willReturn('1');

        // user uid=10 is NOT in "no" conflicts → hasConflict() = true
        $conflictsCollection = new ArrayCollection(['no' => []]);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn(99);
        $paper->method('getRvid')->willReturn(1);
        $paper->method('getReview')->willReturn($review);
        $paper->method('getConflicts')->willReturn($conflictsCollection);

        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_FOLLOW])
        );
    }

    // ── canManage ─────────────────────────────────────────────────────────────

    public function testCanManage_NotOwnPaper(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_SECRETARY'
        );
        $voter = new PapersVoter($security);

        $user = $this->makeUser(5);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->makePaper(99); // uid != user uid
        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE])
        );
    }

    public function testCanManage_OwnPaperDenied(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_SECRETARY'
        );
        $voter = new PapersVoter($security);

        $user = $this->makeUser(5);
        $user->method('hasRole')->willReturn(false);

        $paper = $this->makePaper(5); // same uid as user
        $token = $this->makeToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE])
        );
    }
}
