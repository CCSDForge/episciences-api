<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Repository\PaperConflictsRepository;
use App\Repository\PapersRepository;
use App\Security\Voter\PapersVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PapersVoterTest extends TestCase
{
    private PaperConflictsRepository&MockObject $paperConflictsRepository;
    private PapersRepository&MockObject $papersRepository;
    private PapersVoter $voter;

    protected function setUp(): void
    {
        $this->paperConflictsRepository = $this->createMock(PaperConflictsRepository::class);
        $this->papersRepository = $this->createMock(PapersRepository::class);
        $this->voter = new PapersVoter($this->paperConflictsRepository, $this->papersRepository);
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(int $uid, array $roles = [], ?int $currentJournalId = 1): User&MockObject
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
     * @param array<string, ?string> $settings journal settings
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
        int $paperid = 1,
        array $settings = [],
        ?int $status = null
    ): Paper&MockObject {
        $settings += ['isCoiEnabled' => $coiEnabled ? '1' : null];
        $review = $this->createMock(Review::class);
        $review->method('getSetting')->willReturnCallback(
            static fn(string $key): ?string => $settings[$key] ?? null
        );

        $paper = $this->createMock(Paper::class);
        $paper->method('getUid')->willReturn($uid);
        $paper->method('getStatus')->willReturn($status);
        $paper->method('getRvid')->willReturn($rvid);
        $paper->method('getPaperid')->willReturn($paperid);
        $paper->method('getEditors')->willReturn($this->assignments($editors));
        $paper->method('getReviewers')->willReturn($this->assignments($reviewers));
        $paper->method('getCopyEditors')->willReturn($this->assignments($copyEditors));
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

    /**
     * Assignments grouped by uid, as Paper::getEditors(): a user given without assignments is
     * currently assigned (one active assignment).
     *
     * @param array<int, mixed> $byUid uid => list of UserAssignment, or anything for an active assignment
     * @return array<int, list<UserAssignment>>
     */
    private function assignments(array $byUid): array
    {
        $result = [];

        foreach ($byUid as $uid => $assignments) {
            $result[$uid] = is_array($assignments) && $assignments !== [] && $assignments[array_key_first($assignments)] instanceof UserAssignment
                ? array_values($assignments)
                : [$this->assignment(UserAssignment::STATUS_ACTIVE, '2024-01-01')];
        }

        return $result;
    }

    private function assignment(string $status, string $when, ?int $id = null): UserAssignment
    {
        $assignment = new UserAssignment()->setStatus($status)->setWhen(new \DateTimeImmutable($when));

        if ($id !== null) {
            new \ReflectionProperty(UserAssignment::class, 'id')->setValue($assignment, $id);
        }

        return $assignment;
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

    /**
     * As on the page of a paper in the gpl, the non-public details are shown to its author (isOwner),
     * not to its co-authors.
     */
    public function testCoAuthorCannotViewPaperDetails(): void
    {
        $user  = $this->makeUser(20);
        $paper = $this->makePaper(10, coAuthors: [20 => []]);
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
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
    /**
     * @return array<string, array{list<string>, int, array<string, mixed>}> roles of the token, uid, paper arguments
     */
    public static function tokenWithoutJournalProvider(): array
    {
        return [
            'epiadmin' => [['ROLE_EPIADMIN'], 70, []],
            'secretary' => [['ROLE_SECRETARY'], 70, []],
            'author' => [['ROLE_USER'], 10, []],
            'assigned editor' => [['ROLE_USER'], 70, ['editors' => [70 => []]]],
            'reviewer' => [['ROLE_USER'], 70, ['reviewers' => [70 => []]]],
        ];
    }

    /**
     * A token issued without journal (no journal code at login) opens no paper, whatever its roles and the
     * assignments of the user.
     *
     * @param list<string> $roles
     * @param array<string, mixed> $paperArguments
     */
    #[DataProvider('tokenWithoutJournalProvider')]
    public function testTokenWithoutJournalOpensNoPaper(array $roles, int $uid, array $paperArguments): void
    {
        $token = $this->makeToken($this->makeUser($uid, $roles, currentJournalId: null));
        $paper = $this->makePaper(10, ...$paperArguments);

        foreach ([PapersVoter::PAPERS_VIEW, PapersVoter::PAPERS_EDIT, PapersVoter::PAPERS_MANAGE, PapersVoter::PAPERS_REVIEW, PapersVoter::PAPERS_FOLLOW] as $attribute) {
            $this->assertSame(-1, $this->voter->vote($token, $paper, [$attribute]), $attribute);
        }
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
     * @return array<string, array{int, array<string, mixed>}> uid, paper arguments
     */
    public static function assignedUserOfAnotherJournalProvider(): array
    {
        return [
            'author' => [10, []],
            'co-author' => [70, ['coAuthors' => [70 => []]]],
            'assigned editor' => [70, ['editors' => [70 => []]]],
            'assigned copy editor' => [70, ['copyEditors' => [70 => []]]],
            'reviewer' => [70, ['reviewers' => [70 => []]]],
        ];
    }

    /**
     * A token only opens the papers of its journal: being the author or assigned to a paper of another
     * journal does not open it either.
     *
     * @param array<string, mixed> $paperArguments
     */
    #[DataProvider('assignedUserOfAnotherJournalProvider')]
    public function testAssignedUserWithATokenOfAnotherJournalIsDenied(int $uid, array $paperArguments): void
    {
        $token = $this->makeToken($this->makeUser($uid, ['ROLE_EDITOR', 'ROLE_COPYEDITOR'], currentJournalId: 2));
        $paper = $this->makePaper(10, ...[...$paperArguments, 'rvid' => 1]);

        foreach ([PapersVoter::PAPERS_VIEW, PapersVoter::PAPERS_EDIT, PapersVoter::PAPERS_MANAGE, PapersVoter::PAPERS_REVIEW] as $attribute) {
            $this->assertSame(-1, $this->voter->vote($token, $paper, [$attribute]), $attribute);
        }
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
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // COI enabled, conflicts collection has NO 'no' key → get('no') returns null
        $paper = $this->makePaper(10, editors: [70 => []], coiEnabled: true, conflicts: new ArrayCollection());

        // Must NOT throw TypeError. Editor without answer ("later") → has conflict → canView false
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
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // uid 70 IS in the 'no' group → no conflict → can view as assigned editor
        $conflicts = new ArrayCollection(['no' => [70 => new PaperConflicts()]]);
        $paper     = $this->makePaper(10, editors: [70 => []], coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ v2 article: conflicts loaded by PAPERID (shared across versions)

    public function testV2ArticleLoadsConflictsByPaperIdWhenCoiEnabled(): void
    {
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // v2 article: docid (D2) differs from paperid (P1, shared by all versions).
        // Conflicts are declared on the PAPERID, so they must be loaded by paperid.
        $paperid    = 1234;
        $noConflict = new PaperConflicts()->setPaperId($paperid)->setBy(70)->setAnswer('no');

        $this->paperConflictsRepository->expects($this->once())->method('findByPaperId')
            ->with($paperid)
            ->willReturn([$noConflict]);

        // user 70 is an assigned editor and declared 'no' conflict → no conflict → can view
        $paper = $this->makePaper(10, editors: [70 => []], coiEnabled: true, paperid: $paperid);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testV2ArticleWithoutNoGroupIsDenied(): void
    {
        $user  = $this->makeUser(70, ['ROLE_EDITOR']);
        $token = $this->makeToken($user);

        // v2 article: conflicts exist for the paperid but the user is NOT in the 'no' group
        $paperid       = 1234;
        $declaredOther = new PaperConflicts()->setPaperId($paperid)->setBy(99)->setAnswer('no');

        $this->paperConflictsRepository->method('findByPaperId')
            ->with($paperid)
            ->willReturn([$declaredOther]);

        // user 70 is an assigned editor without answer ("later") → conflict → denied
        $paper = $this->makePaper(10, editors: [70 => []], coiEnabled: true, paperid: $paperid);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ conflicts of interest as in the gpl

    /**
     * A user who cannot declare a conflict (no editorial role) is never in conflict, even without answer.
     */
    public function testReviewerWithoutEditorialRoleViewsWithoutAnswer(): void
    {
        $token = $this->makeToken($this->makeUser(70));
        $paper = $this->makePaper(10, reviewers: [70 => []], coiEnabled: true, conflicts: new ArrayCollection());

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testReviewerWithoutEditorialRoleIsNeverInConflict(): void
    {
        $token = $this->makeToken($this->makeUser(70));
        $paper = $this->makePaper(10, reviewers: [70 => []], coiEnabled: true, conflicts: new ArrayCollection());

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
    }

    public function testEditorWhoAnsweredLaterIsInConflict(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $conflicts = new ArrayCollection(['later' => [70 => new PaperConflicts()]]);
        $paper = $this->makePaper(10, editors: [70 => []], coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testEditorWhoAnsweredNoIsNotInConflict(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $conflicts = new ArrayCollection(['no' => [70 => new PaperConflicts()]]);
        $paper = $this->makePaper(10, editors: [70 => []], coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    /**
     * As in the gpl, a secretary in conflict of interest does not see the details of the paper.
     */
    public function testSecretaryInConflictCannotView(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_SECRETARY']));
        $conflicts = new ArrayCollection(['yes' => [70 => new PaperConflicts()]]);
        $paper = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testSecretaryWithoutConflictCanView(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_SECRETARY']));
        $conflicts = new ArrayCollection(['no' => [70 => new PaperConflicts()]]);
        $paper = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ author of the paper

    /**
     * As on the member page of the paper in the gpl, the author always sees its details,
     * even when they hold an editorial role and are in conflict of interest.
     */
    public function testAuthorInConflictStillViewsOwnPaper(): void
    {
        $token = $this->makeToken($this->makeUser(10, ['ROLE_EDITOR']));
        $conflicts = new ArrayCollection(['yes' => [10 => new PaperConflicts()]]);
        $paper = $this->makePaper(10, coiEnabled: true, conflicts: $conflicts);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    /**
     * Viewing is not managing: the author never manages their own paper.
     */
    public function testAuthorStillCannotManageOwnPaper(): void
    {
        $token = $this->makeToken($this->makeUser(10, ['ROLE_SECRETARY']));

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(10), [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ editors encapsulation (journal settings)

    /**
     * @return array<string, array{list<string>, array<string, ?string>, int}> roles, journal settings, expected papers_view vote
     */
    public static function notAssignedEditorProvider(): array
    {
        return [
            'editor, encapsulation enabled' => [['ROLE_EDITOR'], ['encapsulateEditors' => '1'], -1],
            'editor, encapsulation disabled' => [['ROLE_EDITOR'], ['encapsulateEditors' => '0'], 1],
            'editor, empty setting' => [['ROLE_EDITOR'], ['encapsulateEditors' => ''], -1],
            'editor, setting not set' => [['ROLE_EDITOR'], [], -1],
            'copy editor, encapsulation disabled' => [['ROLE_COPYEDITOR'], ['encapsulateCopyEditors' => '0'], 1],
            'copy editor, encapsulation enabled' => [['ROLE_COPYEDITOR'], ['encapsulateCopyEditors' => '1'], -1],
            'copy editor, setting not set' => [['ROLE_COPYEDITOR'], [], -1],
            'guest editor' => [['ROLE_GUEST_EDITOR'], ['encapsulateEditors' => '0'], -1],
        ];
    }

    /**
     * An editor (copy editor) not assigned to a paper views its details only when the journal sets
     * their encapsulation to "0" (DefaultController::isEditorialStaffOfEveryPaper in the gpl), and
     * never manages it, whatever the encapsulation settings.
     *
     * @param list<string> $roles
     * @param array<string, ?string> $settings
     */
    #[DataProvider('notAssignedEditorProvider')]
    public function testNotAssignedEditorViewsDependingOnEncapsulationButNeverManages(array $roles, array $settings, int $expectedView): void
    {
        $token = $this->makeToken($this->makeUser(70, $roles));
        $paper = $this->makePaper(10, settings: $settings);

        $this->assertSame($expectedView, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));

        foreach ([PapersVoter::PAPERS_EDIT, PapersVoter::PAPERS_MANAGE] as $attribute) {
            $this->assertSame(-1, $this->voter->vote($token, $paper, [$attribute]), $attribute);
        }
    }

    public function testAssignedEditorViewsAndManagesWhateverTheEncapsulation(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $paper = $this->makePaper(10, editors: [70 => []], settings: ['encapsulateEditors' => '1']);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    public function testAssignedCopyEditorManagesWhateverTheEncapsulation(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_COPYEDITOR']));
        $paper = $this->makePaper(10, copyEditors: [70 => []], settings: ['encapsulateCopyEditors' => '1']);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testSecretaryManagesEveryPaperWhateverTheEncapsulation(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_SECRETARY']));
        $paper = $this->makePaper(10, settings: ['encapsulateEditors' => '1']);

        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
    }

    // ------------------------------------------------------------------ status of the assignments

    /**
     * @return array<string, array{string, string, string, int}> role of the assignment, latest status, attribute, expected vote
     */
    public static function latestAssignmentProvider(): array
    {
        return [
            'editor unassigned' => ['editors', UserAssignment::STATUS_INACTIVE, PapersVoter::PAPERS_MANAGE, -1],
            'editor disabled' => ['editors', 'disabled', PapersVoter::PAPERS_VIEW, -1],
            'editor reassigned' => ['editors', UserAssignment::STATUS_ACTIVE, PapersVoter::PAPERS_MANAGE, 1],
            'copy editor unassigned' => ['copyEditors', UserAssignment::STATUS_INACTIVE, PapersVoter::PAPERS_MANAGE, -1],
            'reviewer who declined' => ['reviewers', UserAssignment::STATUS_DECLINED, PapersVoter::PAPERS_VIEW, -1],
            'reviewer still invited' => ['reviewers', UserAssignment::STATUS_PENDING, PapersVoter::PAPERS_REVIEW, -1],
            'reviewer cancelled' => ['reviewers', UserAssignment::STATUS_CANCELLED, PapersVoter::PAPERS_VIEW, -1],
            'reviewer who accepted' => ['reviewers', UserAssignment::STATUS_ACTIVE, PapersVoter::PAPERS_REVIEW, 1],
        ];
    }

    /**
     * As Episciences_PapersManager::getEditors() and getReviewers() in the gpl, only the latest
     * assignment of the user counts, and only when it is active.
     */
    #[DataProvider('latestAssignmentProvider')]
    public function testOnlyTheLatestActiveAssignmentCounts(string $role, string $latestStatus, string $attribute, int $expected): void
    {
        $token = $this->makeToken($this->makeUser(70));
        $history = [
            $this->assignment($latestStatus, '2024-06-01'),
            $this->assignment(UserAssignment::STATUS_ACTIVE, '2024-01-01'),
        ];
        $paper = $this->makePaper(10, ...[$role => [70 => $history]]);

        $this->assertSame($expected, $this->voter->vote($token, $paper, [$attribute]));
    }

    /**
     * @return array<string, array{string, string, int}> status of the assignment of id 1, of id 2, expected vote
     */
    public static function sameDateAssignmentsProvider(): array
    {
        return [
            'unassigned after' => [UserAssignment::STATUS_ACTIVE, UserAssignment::STATUS_INACTIVE, -1],
            'reassigned after' => [UserAssignment::STATUS_INACTIVE, UserAssignment::STATUS_ACTIVE, 1],
        ];
    }

    /**
     * For two assignments of the same date, the latest id wins, as the row kept by the gpl, whatever
     * their order.
     */
    #[DataProvider('sameDateAssignmentsProvider')]
    public function testTheLatestIdWinsForTheSameDate(string $firstStatus, string $secondStatus, int $expected): void
    {
        $token = $this->makeToken($this->makeUser(70));
        $first = $this->assignment($firstStatus, '2024-06-01 10:00:00', 1);
        $second = $this->assignment($secondStatus, '2024-06-01 10:00:00', 2);

        foreach ([[$first, $second], [$second, $first]] as $history) {
            $paper = $this->makePaper(10, editors: [70 => $history]);

            $this->assertSame($expected, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
        }
    }

    // ------------------------------------------------------------------ own paper of an administrator

    /**
     * @return array<string, array{string[]}>
     */
    public static function administratorRolesProvider(): array
    {
        return [
            'administrator only' => [['ROLE_ADMINISTRATOR']],
            'epiadmin' => [['ROLE_EPIADMIN']],
            'secretary' => [['ROLE_SECRETARY']],
        ];
    }

    /**
     * As on the administration page of the gpl, nobody manages or reviews their own paper, whatever
     * their roles; they still view its details.
     *
     * @param string[] $roles
     */
    #[DataProvider('administratorRolesProvider')]
    public function testNobodyManagesOrReviewsTheirOwnPaper(array $roles): void
    {
        $token = $this->makeToken($this->makeUser(10, $roles));
        $paper = $this->makePaper(10);

        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_MANAGE]));
        $this->assertSame(-1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_REVIEW]));
        $this->assertSame(1, $this->voter->vote($token, $paper, [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ latest version of the paper

    /**
     * As Episciences_Paper::isAssignedEditorOrCopyEditor in the gpl, the editor (copy editor) of the
     * latest version of a paper handles its previous versions.
     */
    public function testEditorOfTheLatestVersionHandlesThePreviousOnes(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $previousVersion = $this->makePaper(10);
        $this->papersRepository->method('findLatestVersion')->with($previousVersion)->willReturn($this->makePaper(10, editors: [70 => []]));

        $this->assertSame(1, $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_VIEW]));
        $this->assertSame(1, $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testResetForgetsTheLatestVersions(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $previousVersion = $this->makePaper(10);
        $previousVersion->method('getDocid')->willReturn(42);
        $this->papersRepository->expects($this->exactly(2))->method('findLatestVersion')->willReturn(null);

        $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_MANAGE]);
        $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_MANAGE]);
        $this->voter->reset();
        $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_MANAGE]);
    }

    public function testCopyEditorOfTheLatestVersionHandlesThePreviousOnes(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_COPYEDITOR']));
        $previousVersion = $this->makePaper(10);
        $this->papersRepository->method('findLatestVersion')->willReturn($this->makePaper(10, copyEditors: [70 => []]));

        $this->assertSame(1, $this->voter->vote($token, $previousVersion, [PapersVoter::PAPERS_MANAGE]));
    }

    public function testEditorUnassignedFromTheLatestVersionDoesNotHandleThePreviousOnes(): void
    {
        $token = $this->makeToken($this->makeUser(70, ['ROLE_EDITOR']));
        $history = [$this->assignment(UserAssignment::STATUS_INACTIVE, '2024-06-01'), $this->assignment(UserAssignment::STATUS_ACTIVE, '2024-01-01')];
        $this->papersRepository->method('findLatestVersion')->willReturn($this->makePaper(10, editors: [70 => $history]));

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(10), [PapersVoter::PAPERS_MANAGE]));
    }

    /**
     * The reviewers only review the version they are assigned to.
     */
    public function testReviewerOfTheLatestVersionDoesNotSeeThePreviousOnes(): void
    {
        $token = $this->makeToken($this->makeUser(70));
        $this->papersRepository->method('findLatestVersion')->willReturn($this->makePaper(10, reviewers: [70 => []]));

        $this->assertSame(-1, $this->voter->vote($token, $this->makePaper(10), [PapersVoter::PAPERS_VIEW]));
    }

    // ------------------------------------------------------------------ deleted or removed paper

    /**
     * @return array<string, array{int, int, string[], array<string, mixed>, array<string, int>}> status, uid, roles, paper assignments, expected vote by attribute
     */
    public static function deletedOrRemovedProvider(): array
    {
        $view = PapersVoter::PAPERS_VIEW;
        $manage = PapersVoter::PAPERS_MANAGE;
        $edit = PapersVoter::PAPERS_EDIT;
        $review = PapersVoter::PAPERS_REVIEW;

        return [
            'deleted, its author' => [Paper::STATUS_DELETED, 10, [], [], [$view => -1, $manage => -1, $edit => -1]],
            'deleted, its reviewer' => [Paper::STATUS_DELETED, 70, [], ['reviewers' => [70 => []]], [$view => -1, $review => -1]],
            'deleted, secretary' => [Paper::STATUS_DELETED, 70, ['ROLE_SECRETARY'], [], [$view => 1, $manage => 1, $edit => -1, $review => -1]],
            'removed, assigned editor' => [Paper::STATUS_REMOVED, 70, ['ROLE_EDITOR'], ['editors' => [70 => []]], [$view => 1, $manage => 1, $edit => -1]],
            'removed, epiadmin' => [Paper::STATUS_REMOVED, 70, ['ROLE_EPIADMIN'], [], [$view => 1, $manage => 1, $edit => -1, $review => -1]],
            'removed, its author who is secretary' => [Paper::STATUS_REMOVED, 10, ['ROLE_SECRETARY'], [], [$view => -1, $manage => -1]],
        ];
    }

    /**
     * As in the gpl, the page of a deleted or removed paper is closed to everyone, its author included;
     * its administration page still shows it to the users who manage it, and nobody changes it anymore.
     *
     * @param string[] $roles
     * @param array<string, mixed> $assignments
     * @param array<string, int> $expected
     */
    #[DataProvider('deletedOrRemovedProvider')]
    public function testDeletedOrRemovedPaper(int $status, int $uid, array $roles, array $assignments, array $expected): void
    {
        $token = $this->makeToken($this->makeUser($uid, $roles));
        $paper = $this->makePaper(10, ...$assignments, status: $status);

        foreach ($expected as $attribute => $vote) {
            $this->assertSame($vote, $this->voter->vote($token, $paper, [$attribute]), $attribute);
        }
    }
}
