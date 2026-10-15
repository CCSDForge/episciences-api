<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\OrderExtension;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\AppQueryItemCollectionExtension;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Runs the collection extensions in production order (AppQueryItemCollectionExtension, priority 0,
 * then API Platform's OrderExtension, priority -32) and checks the resulting ORDER BY of GET /api/sections.
 * OrderExtension skips the operation's default order as soon as an ORDER BY exists, so any orderBy()
 * added upstream for Section would silently drop the position sort.
 */
class SectionCollectionOrderTest extends TestCase
{
    private const array EXPECTED_ORDER = ['o.rvid DESC', 'o.position ASC', 'o.sid ASC'];

    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $metadata = new ClassMetadata(Section::class);
        $metadata->setIdentifier(['sid']);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')->with(Section::class)->willReturn($metadata);
    }

    private function getCollectionOperation(): GetCollection
    {
        $resource = (new \ReflectionClass(Section::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if ($operation instanceof GetCollection) {
                return $operation->withUriTemplate(Section::DEFAULT_URI_TEMPLATE);
            }
        }

        self::fail('GetCollection operation not found on Section');
    }

    /**
     * @param array<string, mixed> $context
     * @return list<string>
     */
    private function applyCollectionExtensions(Security $security, array $context = []): array
    {
        $queryBuilder = (new QueryBuilder($this->entityManager))->select('o')->from(Section::class, 'o');
        $nameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $operation = $this->getCollectionOperation();

        (new AppQueryItemCollectionExtension($security))
            ->applyToCollection($queryBuilder, $nameGenerator, Section::class, $operation, $context);
        (new OrderExtension('ASC'))
            ->applyToCollection($queryBuilder, $nameGenerator, Section::class, $operation, $context);

        return array_map('strval', $queryBuilder->getDQLPart('orderBy'));
    }

    private function security(?User $user, bool $isEditor = false): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $role): bool => $isEditor && $role === 'ROLE_EDITOR'
        );

        return $security;
    }

    private function user(?int $journalId): User
    {
        $user = $this->createMock(User::class);
        $user->method('getCurrentJournalID')->willReturn($journalId);

        return $user;
    }

    public function testAnonymousCollectionIsOrderedByJournalThenPosition(): void
    {
        self::assertSame(self::EXPECTED_ORDER, $this->applyCollectionExtensions($this->security(null)));
    }

    public function testAnonymousCollectionFilteredByRvcodeIsOrderedByPosition(): void
    {
        $journal = $this->createMock(Review::class);
        $journal->method('getRvid')->willReturn(3);

        $repository = $this->createMock(ReviewRepository::class);
        $repository->method('getJournalByIdentifier')->with('epijinfo')->willReturn($journal);
        $this->entityManager->method('getRepository')->with(Review::class)->willReturn($repository);

        self::assertSame(
            self::EXPECTED_ORDER,
            $this->applyCollectionExtensions($this->security(null), ['filters' => ['rvcode' => 'epijinfo']])
        );
    }

    public function testConnectedUserWithoutJournalIsOrderedByPosition(): void
    {
        self::assertSame(self::EXPECTED_ORDER, $this->applyCollectionExtensions($this->security($this->user(null))));
    }

    public function testEditorCollectionIsOrderedByPosition(): void
    {
        self::assertSame(
            self::EXPECTED_ORDER,
            $this->applyCollectionExtensions($this->security($this->user(3), true))
        );
    }

    public function testNonEditorMemberCollectionIsOrderedByPosition(): void
    {
        self::assertSame(self::EXPECTED_ORDER, $this->applyCollectionExtensions($this->security($this->user(3))));
    }
}
