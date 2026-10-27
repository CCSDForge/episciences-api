<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PaperConflictsRepository;
use App\Security\Voter\PapersVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PapersVoterTest extends TestCase
{
    private PaperConflictsRepository&MockObject $paperConflictsRepository;
    private PapersVoter $voter;

    protected function setUp(): void
    {
        $this->paperConflictsRepository = $this->createMock(PaperConflictsRepository::class);
        $this->voter = new PapersVoter($this->paperConflictsRepository);
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(int $uid, array $roles = [], ?int $currentJournalId = null): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getCurrentJournalID')->willReturn($currentJournalId);
        $user->method('getRoles')->willReturn($roles);
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
        ?ArrayCollection $conflicts = null,
        int $paperid = 1
    ): Paper&MockObject {
        $review = $this->createMock(Review::class);
        $review->method('getSetting')
            ->with('isCoiEnabled')
            ->willReturn($coiEnabled ? '1' : null);

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn($uid);
        $paper->method('getRvid')->willReturn($rvid);
        $paper->method('getPaperid')->willReturn($paperid);
        $paper->method('getEditors')->willReturn($editors);
        $paper->method('getReviewers')->willReturn($reviewers);
        $paper->method('getCopyEditors')->willReturn($copyEditors);
        $paper->method('getCoAuthors')->willReturn($coAuthors);
        $paper->method('getReview')->willReturn($review);

        $currentConflicts = $conflicts ?? new ArrayCollection();
        $conflictsLoaded  = $conflicts !== null;
        $paper->method('getConflicts')->willReturnCallback(static function () use (&$currentConflicts): \Doctrine\Common\Collections\ArrayCollection {
            return $currentConflicts;
        });
        $paper->method('conflictsLoaded')->willReturnCallback(
            static function () use (&$conflictsLoaded): bool {
                return $conflictsLoaded;
            }
        );
        $paper->method('setConflicts')->willReturnCallback(
            static function (iterable $c) use (&$currentConflicts, &$conflictsLoaded, $paper): Paper {
                $conflictsLoaded = true;
                $items = is_array($c) ? $c : iterator_to_array($c);
                $grouped = [];
                foreach ($items as $conflict) {
                    if ($conflict instanceof PaperConflicts) {
                        $grouped[$conflict->getAnswer()][$conflict->getBy()] = $conflict;
                    }
                }
                $currentConflicts = new ArrayCollection($grouped !== [] ? $grouped : $items);
                return $paper;
            }
        );
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
        $user  = $this->makeUser(1);
        $paper = $this->makePaper(1);
        $token = $this->makeToken($user);
        // author can view → GRANT (1)
        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $user  = $this->makeUser(1);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);
        $this->assertSame(0, $this->voter->vote($token, $paper, ['unknown_attr']));
    }

    public function testAbstainsForNonPaperSubject(): void
    {
        $user  = $this->makeUser(1);
        $token = $this->makeToken($user);
        $this->assertSame(0, $this->voter->vote($token, new \stdClass(), [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ ROLE_EPIADMIN

    public function testEpiAdminOfTheJournalIsAlwaysGranted(): void
    {
        $user  = $this->makeUser(99, ['ROLE_EPIADMIN']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);

        foreach ([PapersVoter::PAPERS_VIEW, PapersVoter::PAPERS_EDIT, PapersVoter::PAPERS_MANAGE, PapersVoter::PAPERS_REVIEW, PapersVoter::PAPERS_FOLLOW] as $attribute) {
            $this->assertSame(1, $this->voter->vote($token, $paper, [$attribute]), $attribute);
        }
    }

    /**
     * As in the gpl, root is never in conflict of interest, even after answering "yes".
     */
    public function testEpiAdminOfTheJournalIsNeverInConflict(): void
    {
        $token = $this->makeToken($this->makeUser(99, ['ROLE_EPIADMIN']));
        $conflicts = new ArrayCollection(['yes' => [99 => new PaperConflicts()]]);
        $paper = $this->makePaper(1, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ anonymous user

    public function testAnonymousUserIsDenied(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $paper = $this->makePaper(1);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ ROLE_ADMINISTRATOR

    public function testAdminWithoutSpecificRolesIsGranted(): void
    {
        $user = $this->makeUser(42, ['ROLE_ADMINISTRATOR']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1, 1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    /** Admin who also has a specific role falls through to attribute-level checks. */
    public function testAdminWithSecretaryRoleFallsThroughToAttributeChecks(): void
    {
        $user = $this->makeUser(42, ['ROLE_ADMINISTRATOR', 'ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1, 1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    // ------------------------------------------------------------------ canView()

    public function testAuthorCanViewOwnPaper(): void
    {
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testCoAuthorCanViewPaper(): void
    {
        $user  = $this->makeUser(20);
        $paper = $this->makePaper(10, coAuthors: [20 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testEditorCanViewPaper(): void
    {
        $user  = $this->makeUser(30);
        $paper = $this->makePaper(10, editors: [30 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testReviewerCanViewPaper(): void
    {
        $user  = $this->makeUser(31);
        $paper = $this->makePaper(10, reviewers: [31 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testCopyEditorCanViewPaper(): void
    {
        $user  = $this->makeUser(32);
        $paper = $this->makePaper(10, copyEditors: [32 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testSecretaryCanViewAnyPaper(): void
    {
        $user  = $this->makeUser(99, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(1);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testUnrelatedUserCannotViewPaper(): void
    {
        $user  = $this->makeUser(99);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ canEdit()

    public function testSecretaryCanEdit(): void
    {
        $user  = $this->makeUser(50, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testAuthorCanEditOwnPaper(): void
    {
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testCopyEditorCanEdit(): void
    {
        $user  = $this->makeUser(60);
        $paper = $this->makePaper(10, copyEditors: [60 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    public function testPaperEditorCanEdit(): void
    {
        $user  = $this->makeUser(61);
        $paper = $this->makePaper(10, editors: [61 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_EDIT]));
    }

    // ------------------------------------------------------------------ canManage()

    public function testAuthorCannotManageOwnPaper(): void
    {
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testSecretaryCanManagePaperByOtherAuthor(): void
    {
        $user  = $this->makeUser(99, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ canReview()

    public function testAuthorCannotReviewOwnPaper(): void
    {
        $user  = $this->makeUser(10, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    public function testReviewerCanReviewPaper(): void
    {
        $user  = $this->makeUser(40);
        $paper = $this->makePaper(10, reviewers: [40 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    public function testSecretaryCanReviewOtherAuthorPaper(): void
    {
        $user  = $this->makeUser(99, ['ROLE_SECRETARY']);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    // ------------------------------------------------------------------ canFollow()

    public function testEditorWithoutConflictCanFollow(): void
    {
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);
        // COI disabled → no conflict → the editor can follow the paper
        $paper = $this->makePaper(10);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_FOLLOW]));
    }

    public function testEditorWithConflictCannotFollow(): void
    {
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // COI enabled, user 70 NOT in 'no' group → hasConflict = true → cannot follow
        $conflicts = new ArrayCollection(['no' => [99 => new PaperConflicts()]]);
        $paper     = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_FOLLOW]));
    }

    public function testNonEditorCannotFollow(): void
    {
        $token = $this->makeToken($this->makeUser(70));

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(10), [PapersVoter::PAPERS_FOLLOW]));
    }

    // ------------------------------------------------------------------ journal of the token

    /**
     * The roles of a token are those of its journal: they do not apply to a paper of another one.
     */
    public function testTokenAdministratorOfAnotherJournalIsDenied(): void
    {
        $user = $this->makeUser(42, ['ROLE_ADMINISTRATOR'], currentJournalId: 2);
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_MANAGE]));
    }

    public function testTokenSecretaryOfAnotherJournalIsDenied(): void
    {
        $user = $this->makeUser(42, ['ROLE_SECRETARY'], currentJournalId: 2);
        $token = $this->makeToken($user);

        foreach ([PapersVoter::PAPERS_VIEW, PapersVoter::PAPERS_EDIT, PapersVoter::PAPERS_MANAGE, PapersVoter::PAPERS_REVIEW] as $attribute) {
            $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [$attribute]), $attribute);
        }
    }

    public function testTokenAdministratorOfThePaperJournalIsGranted(): void
    {
        $user = $this->makeUser(42, ['ROLE_ADMINISTRATOR'], currentJournalId: 1);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_MANAGE]));
    }

    /**
     * As in the gpl, epiadmin is a role of a journal: it does not apply to the papers of another one.
     */
    public function testEpiAdminOfAnotherJournalIsDenied(): void
    {
        $user = $this->makeUser(42, ['ROLE_EPIADMIN'], currentJournalId: 2);
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_MANAGE]));
    }

    /**
     * A user loaded from the database who is epiadmin of another journal only.
     */
    public function testUserEpiAdminOfAnotherJournalOnlyIsDenied(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(42);
        $user->method('getCurrentJournalID')->willReturn(null);
        $user->method('getRoles')->willReturnCallback(
            static fn(?int $rvId = null): array => $rvId === 2 ? ['ROLE_EPIADMIN'] : ['ROLE_USER']
        );
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_MANAGE]));
        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(1, rvid: 2), [PapersVoter::PAPERS_MANAGE]));
    }

    /**
     * A user loaded from the database (no journal of a token): their roles in the journal of the paper.
     */
    public function testUserRolesAreThoseOfTheJournalOfThePaper(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(42);
        $user->method('getCurrentJournalID')->willReturn(null);
        $user->method('getRoles')->willReturnCallback(
            static fn(?int $rvId = null): array => $rvId === 2 ? ['ROLE_SECRETARY'] : ['ROLE_USER']
        );
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_EDIT]));
        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(1, rvid: 2), [PapersVoter::PAPERS_EDIT]));
    }

    /**
     * The roles inherit each other as in Episciences_Auth (gpl): a chief editor is also a secretary.
     */
    public function testChiefEditorInheritsTheSecretaryRole(): void
    {
        $token = $this->makeToken($this->makeUser(42, ['ROLE_CHIEF_EDITOR'], currentJournalId: 1));

        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_EDIT]));
        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_VIEW]));
    }

    /**
     * As in Episciences_Auth::isEditor (gpl), a secretary is not an editor (unlike the Symfony role_hierarchy).
     */
    public function testSecretaryIsNotAnEditorToFollow(): void
    {
        $token = $this->makeToken($this->makeUser(42, ['ROLE_SECRETARY'], currentJournalId: 1));

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(1, rvid: 1), [PapersVoter::PAPERS_FOLLOW]));
    }

    /**
     * Only the journal roles are scoped: being the author of the paper is not a journal role.
     */
    public function testAuthorIsGrantedWhateverTheJournalOfTheToken(): void
    {
        $token = $this->makeToken($this->makeUser(10, ['ROLE_MEMBER'], currentJournalId: 2));

        $this->assertSame(1, $this->voter->vote($token, $this->makePaper(10, rvid: 1), [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ administrator and conflicts of interest

    public function testAdminWhoAnsweredYesToTheConflictIsDenied(): void
    {
        $token = $this->makeToken($this->makeUser(42, ['ROLE_ADMINISTRATOR']));
        $conflicts = new ArrayCollection(['yes' => [42 => new PaperConflicts()]]);
        $paper = $this->makePaper(1, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    /**
     * An administrator only cannot declare a conflict: without an explicit "yes", they are granted.
     */
    public function testAdminWhoAnsweredLaterOrNothingIsGranted(): void
    {
        $token = $this->makeToken($this->makeUser(42, ['ROLE_ADMINISTRATOR']));
        $conflicts = new ArrayCollection(['later' => [42 => new PaperConflicts()]]);
        $paper = $this->makePaper(1, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testAdminAnswerIsIgnoredWhenCoiDisabled(): void
    {
        $token = $this->makeToken($this->makeUser(42, ['ROLE_ADMINISTRATOR']));
        $conflicts = new ArrayCollection(['yes' => [42 => new PaperConflicts()]]);
        $paper = $this->makePaper(1, coiEnabled: false, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ hasConflict() — null-safety regression

    /**
     * Regression: array_key_exists on null caused TypeError when no 'no' answer group existed.
     */
    public function testHasConflictDoesNotThrowWhenNoNoGroup(): void
    {
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
        $user  = $this->makeUser(10);
        $token = $this->makeToken($user);
        $paper = $this->makePaper(10); // COI disabled by default

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testUserInNoGroupHasNoConflict(): void
    {
        $user  = $this->makeUser(70);
        $token = $this->makeToken($user);

        // uid 70 IS in the 'no' group → no conflict → can view if co-author
        $conflicts = new ArrayCollection(['no' => [70 => new PaperConflicts()]]);
        $paper     = $this->makePaper(10, coAuthors: [70 => []], coiEnabled: true, conflicts: $conflicts);
        $user->method('hasRole')->willReturn(false);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ v2 article: conflicts loaded by PAPERID (shared across versions)

    public function testV2ArticleLoadsConflictsByPaperIdWhenCoiEnabled(): void
    {
        $user  = $this->makeUser(70);
        $token = $this->makeToken($user);

        // v2 article: docid (D2) differs from paperid (P1, shared by all versions).
        // Conflicts are declared on the PAPERID, so they must be loaded by paperid.
        $paperid    = 1234;
        $noConflict = new PaperConflicts()->setPaperId($paperid)->setBy(70)->setAnswer('no');

        $this->paperConflictsRepository->method('findByPaperId')
            ->with($paperid)
            ->willReturn([$noConflict]);

        // user 70 is co-author and declared 'no' conflict → no conflict → can view
        $paper = $this->makePaper(10, coAuthors: [70 => []], coiEnabled: true, paperid: $paperid);
        $user->method('hasRole')->willReturn(false);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testV2ArticleWithoutNoGroupIsDenied(): void
    {
        $user  = $this->makeUser(70);
        $token = $this->makeToken($user);

        // v2 article: conflicts exist for the paperid but the user is NOT in the 'no' group
        $paperid       = 1234;
        $declaredOther = new PaperConflicts()->setPaperId($paperid)->setBy(99)->setAnswer('no');

        $this->paperConflictsRepository->method('findByPaperId')
            ->with($paperid)
            ->willReturn([$declaredOther]);

        // user 70 is co-author but has a conflict (not in 'no' group) → denied
        $paper = $this->makePaper(10, coAuthors: [70 => []], coiEnabled: true, paperid: $paperid);
        $user->method('hasRole')->willReturn(false);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }
}
