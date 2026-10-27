<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\AppQueryItemCollectionExtension;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Checks the restrictions added by AppQueryItemCollectionExtension to GET /api/papers/ for each kind of user.
 */
class PaperPrivateCollectionTest extends TestCase
{
    private const int TOKEN_JOURNAL = 8;
    private const int OTHER_JOURNAL = 3;
    private const int UID = 1234;

    private EntityManagerInterface&MockObject $entityManager;

    /** @var array<string, ?string> settings of the journal of the token */
    private array $tokenJournalSettings = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getExpressionBuilder')->willReturn(new Expr());
        $this->entityManager->method('createQueryBuilder')->willReturnCallback(
            fn(): QueryBuilder => new QueryBuilder($this->entityManager)
        );

        $repository = $this->createMock(ReviewRepository::class);
        $repository->method('getJournalByIdentifier')->willReturnCallback(function (string $code): Review {
            $journal = $this->createMock(Review::class);
            $journal->method('getRvid')->willReturn($code === 'dev' ? self::TOKEN_JOURNAL : self::OTHER_JOURNAL);

            return $journal;
        });
        $repository->method('findOneBy')->willReturnCallback(function (array $criteria): ?Review {
            if ($criteria !== ['rvid' => self::TOKEN_JOURNAL]) {
                return null;
            }

            $journal = $this->createMock(Review::class);
            $journal->method('getSetting')->willReturnCallback(
                fn(string $key): ?string => $this->tokenJournalSettings[$key] ?? null
            );

            return $journal;
        });

        $this->entityManager->method('getRepository')->with(Review::class)->willReturn($repository);
    }

    private function getCollectionOperation(): GetCollection
    {
        $resource = (new \ReflectionClass(Paper::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation instanceof GetCollection) {
                return $operation->withName(Paper::COLLECTION_NAME);
            }
        }

        self::fail('GetCollection operation not found on Paper');
    }

    /**
     * @param string[] $grantedRoles
     */
    private function security(?User $user, array $grantedRoles = []): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => in_array($role, $grantedRoles, true)
        );

        return $security;
    }

    private function user(?int $journalId = self::TOKEN_JOURNAL): User
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn($journalId);
        $user->method('getUid')->willReturn(self::UID);

        return $user;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function apply(Security $security, array $filters = []): QueryBuilder
    {
        $queryBuilder = (new QueryBuilder($this->entityManager))->select('o')->from(Paper::class, 'o');
        $operation = $this->getCollectionOperation();

        (new AppQueryItemCollectionExtension($security))->applyToCollection(
            $queryBuilder,
            $this->createMock(QueryNameGeneratorInterface::class),
            Paper::class,
            $operation,
            ['filters' => $filters, 'operation' => $operation]
        );

        return $queryBuilder;
    }

    private function applyToItem(Security $security): QueryBuilder
    {
        $queryBuilder = (new QueryBuilder($this->entityManager))->select('o')->from(Paper::class, 'o')
            ->andWhere('o.docid = :docid')->setParameter('docid', 42);

        (new AppQueryItemCollectionExtension($security))->applyToItem(
            $queryBuilder,
            $this->createMock(QueryNameGeneratorInterface::class),
            Paper::class,
            ['docid' => 42],
            null,
            []
        );

        return $queryBuilder;
    }

    private function where(QueryBuilder $queryBuilder): string
    {
        return (string)$queryBuilder->getDQLPart('where');
    }

    private function parameter(QueryBuilder $queryBuilder, string $name): mixed
    {
        return $queryBuilder->getParameter($name)?->getValue();
    }

    public function testAnonymousUserOnlySeesPublishedPapers(): void
    {
        $queryBuilder = $this->apply($this->security(null));

        self::assertStringContainsString('o.status= :published', $this->where($queryBuilder));
        self::assertSame(Paper::STATUS_PUBLISHED, $this->parameter($queryBuilder, 'published'));
    }

    public function testSecretarySeesAllPapersOfTheTokenJournal(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_SECRETARY']));

        self::assertStringContainsString('o.rvid = :rvId', $this->where($queryBuilder));
        self::assertStringNotContainsString('o.user', $this->where($queryBuilder));
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));
    }

    public function testEditorOnlySeesAssignedPapersOfTheTokenJournal(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_EDITOR']));

        self::assertStringContainsString('uAss.uid = :to', $this->where($queryBuilder));
        self::assertStringContainsString('o.rvid = :rvId', $this->where($queryBuilder));
        self::assertSame(self::UID, $this->parameter($queryBuilder, 'to'));
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));
    }

    /**
     * The role of a copy editor is ROLE_COPYEDITOR (role id "copyeditor").
     */
    public function testCopyEditorOnlySeesAssignedPapersOfTheTokenJournal(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_COPYEDITOR']));

        self::assertStringContainsString('uAss.uid = :to', $this->where($queryBuilder));
        self::assertStringNotContainsString('o.user', $this->where($queryBuilder));
        self::assertSame(self::UID, $this->parameter($queryBuilder, 'to'));
    }

    /**
     * @return iterable<string, array{string[], array<string, ?string>, bool}> roles, journal settings, every paper of the journal
     */
    public static function encapsulationProvider(): iterable
    {
        yield 'editor, encapsulation disabled ("0")' => [['ROLE_EDITOR'], ['encapsulateEditors' => '0'], true];
        yield 'editor, encapsulation enabled' => [['ROLE_EDITOR'], ['encapsulateEditors' => '1'], false];
        yield 'editor, empty setting' => [['ROLE_EDITOR'], ['encapsulateEditors' => ''], false];
        yield 'editor, setting not set' => [['ROLE_EDITOR'], [], false];
        yield 'editor, only the copy editors not encapsulated' => [['ROLE_EDITOR'], ['encapsulateCopyEditors' => '0'], false];
        yield 'copy editor, encapsulation disabled ("0")' => [['ROLE_COPYEDITOR'], ['encapsulateCopyEditors' => '0'], true];
        yield 'copy editor, setting not set' => [['ROLE_COPYEDITOR'], [], false];
        yield 'guest editor' => [['ROLE_GUEST_EDITOR'], ['encapsulateEditors' => '0'], false];
    }

    /**
     * As isEditorialStaffOfEveryPaper in the gpl, an editor (copy editor) reads every paper of the journal
     * only when the journal sets their encapsulation to "0"; otherwise, only the papers they are assigned to.
     *
     * @param string[] $roles
     * @param array<string, ?string> $settings
     */
    #[DataProvider('encapsulationProvider')]
    public function testEditorReadsEveryPaperOnlyWhenNotEncapsulated(array $roles, array $settings, bool $everyPaper): void
    {
        $this->tokenJournalSettings = $settings;
        $queryBuilder = $this->apply($this->security($this->user(), $roles));
        $where = $this->where($queryBuilder);

        self::assertStringContainsString('o.rvid = :rvId', $where);
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));

        if ($everyPaper) {
            self::assertStringNotContainsString('uAss.uid', $where);
            self::assertStringNotContainsString('o.user', $where);
        } else {
            self::assertStringContainsString('uAss.uid = :to', $where);
        }
    }

    public function testAuthorOnlySeesOwnPapersOfTheTokenJournal(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_MEMBER']));

        self::assertStringContainsString('o.user= :currentUser', $this->where($queryBuilder));
        self::assertStringContainsString('o.rvid = :rvId', $this->where($queryBuilder));
        self::assertSame(self::UID, $this->parameter($queryBuilder, 'currentUser'));
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));
    }

    /**
     * As isRestrictedAccess in the gpl, an author also sees the papers of which they are a co-author.
     */
    public function testAuthorAlsoSeesThePapersOfWhichTheyAreCoAuthor(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_MEMBER']));
        $where = $this->where($queryBuilder);

        self::assertStringContainsString('o.user= :currentUser OR (EXISTS(', $where);
        self::assertStringContainsString('coAuthorAss.itemid = o.docid', $where);
        self::assertStringContainsString('coAuthorAss.uid = :currentUser', $where);
        self::assertSame(UserAssignment::ROLE_CO_AUTHOR, $this->parameter($queryBuilder, 'coAuthorRole'));
        self::assertSame(UserAssignment::ITEM_PAPER, $this->parameter($queryBuilder, 'coAuthorItem'));
    }

    /**
     * @return iterable<string, array{string[]}>
     */
    public static function connectedRolesProvider(): iterable
    {
        yield 'secretary' => [['ROLE_SECRETARY']];
        yield 'editor' => [['ROLE_EDITOR']];
        yield 'author' => [['ROLE_MEMBER']];
    }

    /**
     * @param string[] $roles
     */
    #[DataProvider('connectedRolesProvider')]
    public function testRvcodeFilterAppliesInConnectedMode(array $roles): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), $roles), ['rvcode' => 'other']);

        self::assertStringContainsString('o.rvid = :requestedRvId', $this->where($queryBuilder));
        self::assertSame(self::OTHER_JOURNAL, $this->parameter($queryBuilder, 'requestedRvId'));
    }

    /**
     * @param string[] $roles
     */
    #[DataProvider('connectedRolesProvider')]
    public function testYearFilterAppliesInConnectedMode(array $roles): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), $roles), ['year' => ['2023', '2024']]);

        self::assertStringContainsString("YEAR(o.publicationDate) = '2023'", $this->where($queryBuilder));
        self::assertStringContainsString("YEAR(o.publicationDate) = '2024'", $this->where($queryBuilder));
    }

    public function testNoFilterAddsNoExtraCondition(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_MEMBER']));

        self::assertNull($queryBuilder->getParameter('requestedRvId'));
        self::assertStringNotContainsString('YEAR(', $this->where($queryBuilder));
    }

    // ------------------------------------------------------------------ GET /api/papers/{docid}

    /**
     * @return iterable<string, array{string[]}>
     */
    public static function itemRolesProvider(): iterable
    {
        yield 'secretary' => [['ROLE_SECRETARY']];
        yield 'editor' => [['ROLE_EDITOR']];
        yield 'reviewer' => [['ROLE_REVIEWER']];
        yield 'author' => [['ROLE_MEMBER']];
    }

    /**
     * As isRestrictedAccess in the gpl, a published paper is open to every connected user, whatever the
     * journal of their token; the other papers stay restricted to what their role opens.
     *
     * @param string[] $roles
     */
    #[DataProvider('itemRolesProvider')]
    public function testItemIsReadableWhenPublishedOrOpenedByTheRole(array $roles): void
    {
        $queryBuilder = $this->applyToItem($this->security($this->user(), $roles));
        $where = $this->where($queryBuilder);

        self::assertStringStartsWith('o.docid = :docid AND (o.status = :publishedStatus OR ', $where);
        self::assertStringContainsString('o.rvid = :rvId', $where);
        self::assertSame(Paper::STATUS_PUBLISHED, $this->parameter($queryBuilder, 'publishedStatus'));
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));
    }

    /**
     * The collection keeps listing the papers opened by the role only (the workspace of the user).
     *
     * @param string[] $roles
     */
    #[DataProvider('itemRolesProvider')]
    public function testCollectionDoesNotAddThePublishedPapers(array $roles): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), $roles));

        self::assertStringNotContainsString(':publishedStatus', $this->where($queryBuilder));
        self::assertNull($queryBuilder->getParameter('publishedStatus'));
    }
}
