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

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        $repository = $this->createMock(ReviewRepository::class);
        $repository->method('getJournalByIdentifier')->willReturnCallback(function (string $code): Review {
            $journal = $this->createMock(Review::class);
            $journal->method('getRvid')->willReturn($code === 'dev' ? self::TOKEN_JOURNAL : self::OTHER_JOURNAL);

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

    public function testAuthorOnlySeesOwnPapersOfTheTokenJournal(): void
    {
        $queryBuilder = $this->apply($this->security($this->user(), ['ROLE_MEMBER']));

        self::assertStringContainsString('o.user= :currentUser', $this->where($queryBuilder));
        self::assertStringContainsString('o.rvid = :rvId', $this->where($queryBuilder));
        self::assertSame(self::UID, $this->parameter($queryBuilder, 'currentUser'));
        self::assertSame(self::TOKEN_JOURNAL, $this->parameter($queryBuilder, 'rvId'));
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
}
