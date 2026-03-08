<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\AppQueryItemCollectionExtension;
use App\Entity\News;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\User;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class AppQueryItemCollectionExtensionTest extends TestCase
{
    private Security&MockObject $security;
    private AppQueryItemCollectionExtension $extension;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->extension = new AppQueryItemCollectionExtension($this->security);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Creates a fluent QueryBuilder stub (every chainable method returns $this).
     * Override specific methods in each test using additional ->method() calls before
     * passing the QB to the extension.
     */
    private function makeQb(string $alias = 'p'): QueryBuilder&MockObject
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn([$alias]);
        $qb->method('getAllAliases')->willReturn([$alias]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        // Default: no parameters (used in adnWherePublished for paper look-up by docid)
        $qb->method('getParameters')->willReturn(new ArrayCollection());
        $qb->method('expr')->willReturn(new Expr());

        return $qb;
    }

    private function makeQng(): QueryNameGeneratorInterface&MockObject
    {
        return $this->createMock(QueryNameGeneratorInterface::class);
    }

    private function makeQbWithEm(string $alias = 'p'): array
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $qb = $this->makeQb($alias);
        $qb->method('getEntityManager')->willReturn($em);
        return [$qb, $em];
    }

    // ── applyToCollection: journal resolution ─────────────────────────────────

    public function testApplyToCollectionThrowsWhenJournalNotFound(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found Journal/');

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->with('unknown')->willReturn(null);

        [$qb, $em] = $this->makeQbWithEm();
        $em->method('getRepository')->with(Review::class)->willReturn($reviewRepo);

        $this->security->method('getUser')->willReturn(null);

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => ['rvcode' => 'unknown']]
        );
    }

    public function testApplyToCollectionWithKnownJournalDoesNotThrow(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(7);

        $reviewRepo = $this->createMock(ReviewRepository::class);
        $reviewRepo->method('getJournalByIdentifier')->with('myjournal')->willReturn($journal);

        [$qb, $em] = $this->makeQbWithEm();
        $em->method('getRepository')->with(Review::class)->willReturn($reviewRepo);

        $this->security->method('getUser')->willReturn(null);

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => ['rvcode' => 'myjournal']]
        );

        $this->assertTrue(true); // no exception
    }

    public function testApplyToCollectionWithoutRvcodeSkipsJournalQuery(): void
    {
        $qb = $this->makeQb();
        // getEntityManager must not be called if there is no rvcode filter
        $qb->expects($this->never())->method('getEntityManager');

        $this->security->method('getUser')->willReturn(null);

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertTrue(true);
    }

    // ── applyToItem ───────────────────────────────────────────────────────────

    public function testApplyToItemDoesNotThrowForAnonymousUser(): void
    {
        $qb = $this->makeQb();
        $this->security->method('getUser')->willReturn(null);

        $this->extension->applyToItem(
            $qb,
            $this->makeQng(),
            Paper::class,
            ['docid' => 123],
            new GetCollection(),
            []
        );

        $this->assertTrue(true);
    }

    // ── Review resource: portal + status filters always applied ───────────────

    public function testReviewCollectionAlwaysFiltersPortalAndDisabledStatus(): void
    {
        $qb = $this->makeQb('rv');
        $this->security->method('getUser')->willReturn(null);

        $capturedConditions = [];
        $qb->method('andWhere')->willReturnCallback(
            function (string $condition) use ($qb, &$capturedConditions): QueryBuilder {
                $capturedConditions[] = $condition;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Review::class,
            new GetCollection(),
            ['filters' => []]
        );

        $merged = implode(' ', $capturedConditions);
        $this->assertStringContainsString('portal', $merged, 'Should filter out portal journal');
        $this->assertStringContainsString('status', $merged, 'Should filter out disabled-status journals');
    }

    public function testReviewItemAlwaysFiltersPortalAndDisabledStatus(): void
    {
        $qb = $this->makeQb('rv');
        $this->security->method('getUser')->willReturn(null);

        $capturedConditions = [];
        $qb->method('andWhere')->willReturnCallback(
            function (string $condition) use ($qb, &$capturedConditions): QueryBuilder {
                $capturedConditions[] = $condition;
                return $qb;
            }
        );

        $this->extension->applyToItem(
            $qb,
            $this->makeQng(),
            Review::class,
            ['rvid' => 1],
            new GetCollection(),
            []
        );

        $merged = implode(' ', $capturedConditions);
        $this->assertStringContainsString('portal', $merged, 'Should filter out portal journal');
        $this->assertStringContainsString('status', $merged, 'Should filter out disabled-status journals');
    }

    // ── Public access: unauthenticated user ───────────────────────────────────

    public function testUnauthenticatedUserGetsPaperCollectionFilteredByPublishedStatus(): void
    {
        $qb = $this->makeQb('p');
        $this->security->method('getUser')->willReturn(null);

        $capturedConditions = [];
        $qb->method('andWhere')->willReturnCallback(
            function (string $condition) use ($qb, &$capturedConditions): QueryBuilder {
                $capturedConditions[] = $condition;
                return $qb;
            }
        );

        $capturedParams = [];
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('published', $capturedParams);
        $this->assertSame(Paper::STATUS_PUBLISHED, $capturedParams['published']);
    }

    // ── Connected user without journal → public access ────────────────────────

    public function testConnectedUserWithoutCurrentJournalUsesPublicAccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(null);

        $this->security->method('getUser')->willReturn($user);

        $capturedParams = [];
        $qb = $this->makeQb('p');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        // public access applies STATUS_PUBLISHED filter
        $this->assertArrayHasKey('published', $capturedParams);
        $this->assertSame(Paper::STATUS_PUBLISHED, $capturedParams['published']);
    }

    // ── Connected user with journal → private access for User resource ────────

    public function testConnectedUserWithJournalJoinsUserRolesForUserResource(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(5);
        $user->method('getUid')->willReturn(42);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturn(false);

        $qb = $this->makeQb('u');

        $joinCalled = false;
        $qb->method('join')->willReturnCallback(
            function () use ($qb, &$joinCalled): QueryBuilder {
                $joinCalled = true;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            User::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertTrue($joinCalled, 'Should JOIN UserRoles for User resource in private access mode');
    }

    // ── Connected secretary gets full Paper access ────────────────────────────

    public function testSecretaryGetsPapersByJournalRvid(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(10);
        $user->method('getUid')->willReturn(99);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_SECRETARY'
        );

        $capturedParams = [];
        $qb = $this->makeQb('p');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        // Secretary access: papers filtered by rvId
        $this->assertArrayHasKey('rvId', $capturedParams);
        $this->assertSame(10, $capturedParams['rvId']);
    }

    // ── Reviewer/editor: only assigned papers ─────────────────────────────────

    public function testReviewerGetsOnlyAssignedPapers(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(3);
        $user->method('getUid')->willReturn(55);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_REVIEWER'
        );

        $joinedTables = [];
        $qb = $this->makeQb('p');
        $qb->method('join')->willReturnCallback(
            function (string $table) use ($qb, &$joinedTables): QueryBuilder {
                $joinedTables[] = $table;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        // Should join UserAssignment table
        $this->assertNotEmpty($joinedTables, 'Should JOIN at least one table for reviewer access');
    }

    // ── Author branch: papers belonging to the authenticated user ─────────────

    public function testAuthorGetsOwnPapersOnly(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(8);
        $user->method('getUid')->willReturn(77);

        $this->security->method('getUser')->willReturn($user);
        // Not secretary, not editor/reviewer/copy-editor/guest-editor
        $this->security->method('isGranted')->willReturn(false);

        $capturedParams = [];
        $qb = $this->makeQb('p');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('currentUser', $capturedParams);
        $this->assertSame(77, $capturedParams['currentUser']);
    }

    // ── privateAccessProcess: Volume/Section + non-editor → adnWherePublished ─

    public function testNonEditorWithJournalGetsPublishedVolumes(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(5);
        $user->method('getUid')->willReturn(10);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturn(false); // not editor, not secretary

        $capturedConditions = [];
        $qb = $this->makeQb('v');
        $qb->method('andWhere')->willReturnCallback(
            function (string $cond) use ($qb, &$capturedConditions): QueryBuilder {
                $capturedConditions[] = $cond;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Volume::class,
            new GetCollection(),
            ['filters' => []]
        );

        // adnWherePublished uses 'papers_a1.status' field and sets 'published' param — or uses andWhere
        $merged = implode(' ', $capturedConditions);
        $this->assertStringContainsString('published', $merged);
    }

    public function testNonEditorWithJournalGetsPublishedSections(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(5);
        $user->method('getUid')->willReturn(10);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturn(false);

        $capturedParams = [];
        $qb = $this->makeQb('s');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Section::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('published', $capturedParams);
        $this->assertSame(Paper::STATUS_PUBLISHED, $capturedParams['published']);
    }

    // ── privateAccessProcess: Volume + editor with matching URI template ───────

    public function testEditorWithVolumeUriTemplateFiltersbyRvId(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(12);
        $user->method('getUid')->willReturn(20);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_EDITOR'
        );

        $capturedParams = [];
        $qb = $this->makeQb('v');
        $qb->method('where')->willReturnCallback(
            function (string $cond) use ($qb): QueryBuilder { return $qb; }
        );
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $operation = new Get(uriTemplate: Volume::DEFAULT_URI_TEMPLATE . '/1');

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Volume::class,
            $operation,
            ['filters' => []]
        );

        $this->assertArrayHasKey('rvId', $capturedParams);
        $this->assertSame(12, $capturedParams['rvId']);
    }

    public function testEditorWithoutMatchingUriTemplateDoesNotFilterByRvId(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(12);
        $user->method('getUid')->willReturn(20);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $role === 'ROLE_EDITOR'
        );

        $capturedParams = [];
        $qb = $this->makeQb('v');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        // URI template does NOT start with Volume::DEFAULT_URI_TEMPLATE or Section::DEFAULT_URI_TEMPLATE
        $operation = new Get(uriTemplate: '/other-resource/1');

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Volume::class,
            $operation,
            ['filters' => []]
        );

        $this->assertArrayNotHasKey('rvId', $capturedParams);
    }

    // ── publicAccessProcess: Volume/Section classes ────────────────────────────

    public function testPublicAccessForVolumeAppliesPublishedFilter(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $capturedParams = [];
        $qb = $this->makeQb('v');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Volume::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('published', $capturedParams);
        $this->assertSame(Paper::STATUS_PUBLISHED, $capturedParams['published']);
    }

    public function testPublicAccessForSectionAppliesPublishedFilter(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $capturedParams = [];
        $qb = $this->makeQb('s');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Section::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('published', $capturedParams);
        $this->assertSame(Paper::STATUS_PUBLISHED, $capturedParams['published']);
    }

    // ── News resource: visibility filter always applied ────────────────────────

    public function testNewsCollectionAlwaysFiltersVisibility(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $capturedParams = [];
        $qb = $this->makeQb('n');
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$capturedParams): QueryBuilder {
                $capturedParams[$name] = $value;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            News::class,
            new GetCollection(),
            ['filters' => []]
        );

        $this->assertArrayHasKey('visibility', $capturedParams);
        $this->assertSame('public', $capturedParams['visibility']);
    }

    // ── publicAccessProcess: only_accepted branch ─────────────────────────────

    public function testPublicAccessOnlyAcceptedBranchCallsAdnWhereAcceptedOnly(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $addOrderByCalled = false;
        $qb = $this->makeQb('p');
        $qb->method('addOrderBy')->willReturnCallback(
            function () use ($qb, &$addOrderByCalled): QueryBuilder {
                $addOrderByCalled = true;
                return $qb;
            }
        );

        $operation = new GetCollection(name: Paper::COLLECTION_NAME);

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            $operation,
            ['filters' => ['only_accepted' => 'true']]
        );

        // adnWhereAcceptedOnly calls addOrderBy('modificationDate', 'DESC')
        $this->assertTrue($addOrderByCalled, 'Expected addOrderBy called from adnWhereAcceptedOnly');
    }

    // ── privateAccessProcess: only_accepted branch ────────────────────────────

    public function testPrivateAccessOnlyAcceptedCallsAdnWhereAcceptedOnly(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn(4);
        $user->method('getUid')->willReturn(33);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->willReturn(false); // author branch

        $addOrderByCalled = false;
        $qb = $this->makeQb('p');
        $qb->method('addOrderBy')->willReturnCallback(
            function () use ($qb, &$addOrderByCalled): QueryBuilder {
                $addOrderByCalled = true;
                return $qb;
            }
        );

        $this->extension->applyToCollection(
            $qb,
            $this->makeQng(),
            Paper::class,
            new GetCollection(),
            ['filters' => ['only_accepted' => 'true']]
        );

        $this->assertTrue($addOrderByCalled, 'Expected addOrderBy called from adnWhereAcceptedOnly');
    }
}
