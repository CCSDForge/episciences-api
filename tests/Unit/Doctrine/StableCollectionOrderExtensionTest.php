<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\OrderExtension;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\AppQueryItemCollectionExtension;
use App\Doctrine\StableCollectionOrderExtension;
use App\Entity\News;
use App\Entity\Paper;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The identifier of the root entity ends the ORDER BY of the collections, so that LIMIT/OFFSET
 * pages are stable when several items share the same sort values.
 */
class StableCollectionOrderExtensionTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $paper = new ClassMetadata(Paper::class);
        $paper->setIdentifier(['docid']);
        $news = new ClassMetadata(News::class);
        $news->setIdentifier(['id']);
        $metadata = [Paper::class => $paper, News::class => $news];

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnCallback(
            static fn(string $class): ClassMetadata => $metadata[$class]
        );
        $this->entityManager = $entityManager;
    }

    /**
     * @return list<string>
     */
    private static function orderBy(QueryBuilder $queryBuilder): array
    {
        $orderBy = $queryBuilder->getDQLPart('orderBy');

        return array_values(array_map(
            static fn(mixed $part): string => $part instanceof OrderBy ? (string)$part : '',
            is_array($orderBy) ? $orderBy : []
        ));
    }

    /**
     * @param class-string $class
     * @param list<array{string, string}> $orderBy
     * @return list<string>
     */
    private function apply(string $class, array $orderBy): array
    {
        $queryBuilder = (new QueryBuilder($this->entityManager))->select('o')->from($class, 'o');

        foreach ($orderBy as [$field, $direction]) {
            $queryBuilder->addOrderBy($field, $direction);
        }

        (new StableCollectionOrderExtension())
            ->applyToCollection($queryBuilder, $this->createStub(QueryNameGeneratorInterface::class), $class);

        return self::orderBy($queryBuilder);
    }

    /**
     * @return iterable<string, array{list<array{string, string}>, list<string>}>
     */
    public static function orderProvider(): iterable
    {
        yield 'descending sort' => [[['o.publicationDate', 'DESC']], ['o.publicationDate DESC', 'o.docid DESC']];
        yield 'ascending sort' => [[['o.title', 'ASC']], ['o.title ASC', 'o.docid ASC']];
        yield 'follows the last criterion' => [
            [['o.rvid', 'DESC'], ['o.title', 'ASC']],
            ['o.rvid DESC', 'o.title ASC', 'o.docid ASC'],
        ];
        yield 'identifier already sorted on' => [
            [['o.docid', 'ASC'], ['o.title', 'DESC']],
            ['o.docid ASC', 'o.title DESC'],
        ];
        yield 'no sort' => [[], []];
    }

    /**
     * @param list<array{string, string}> $orderBy
     * @param list<string> $expected
     */
    #[DataProvider('orderProvider')]
    public function testIdentifierEndsTheOrder(array $orderBy, array $expected): void
    {
        self::assertSame($expected, $this->apply(Paper::class, $orderBy));
    }

    public function testAnotherIdentifierName(): void
    {
        self::assertSame(['o.date_creation DESC', 'o.id DESC'], $this->apply(News::class, [['o.date_creation', 'DESC']]));
    }

    public function testForeignIdentifierIsNotSortedOn(): void
    {
        $this->entityManager->getClassMetadata(Paper::class)->containsForeignIdentifier = true;

        self::assertSame(['o.title ASC'], $this->apply(Paper::class, [['o.title', 'ASC']]));
    }

    /**
     * Collection extensions in production order: the papers sharing a date are sorted by docid.
     *
     * @return list<string>
     */
    private function applyPaperExtensions(Security $security): array
    {
        $queryBuilder = (new QueryBuilder($this->entityManager))->select('o')->from(Paper::class, 'o');
        $nameGenerator = $this->createStub(QueryNameGeneratorInterface::class);
        $operation = new GetCollection(order: ['rvid' => 'DESC']);

        (new AppQueryItemCollectionExtension($security))->applyToCollection($queryBuilder, $nameGenerator, Paper::class, $operation);
        (new OrderExtension('ASC'))->applyToCollection($queryBuilder, $nameGenerator, Paper::class, $operation);
        (new StableCollectionOrderExtension())->applyToCollection($queryBuilder, $nameGenerator, Paper::class, $operation);

        return self::orderBy($queryBuilder);
    }

    public function testPublishedPapersAreSortedByDateThenDocid(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertSame(['o.publicationDate DESC', 'o.docid DESC'], $this->applyPaperExtensions($security));
    }

    public function testSecretaryPapersAreSortedBySubmissionDateThenDocid(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getCurrentJournalID')->willReturn(3);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(static fn(string $role): bool => $role === 'ROLE_SECRETARY');

        self::assertSame(['o.when DESC', 'o.docid DESC'], $this->applyPaperExtensions($security));
    }
}
