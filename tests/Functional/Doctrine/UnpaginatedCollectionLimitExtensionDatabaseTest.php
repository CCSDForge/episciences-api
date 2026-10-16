<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Tests\Fixtures\Doctrine\Entity\LimitChild;
use App\Tests\Fixtures\Doctrine\Entity\LimitParent;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Runs UnpaginatedCollectionLimitExtension against a real MySQL database, with a to-many
 * fetch join: the limit must apply to the root entities, not to the SQL rows.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops its own tables (test_unpaginated_limit_*).
 */
class UnpaginatedCollectionLimitExtensionDatabaseTest extends TestCase
{
    private const int MAX_ITEMS = 2;
    private const int PARENTS = 3;
    private const int CHILDREN_PER_PARENT = 3;

    private EntityManagerInterface $entityManager;
    private SchemaTool $schemaTool;

    /** @var array<int, \Doctrine\ORM\Mapping\ClassMetadata<object>> */
    private array $metadata;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL') ?: ($_SERVER['TEST_DATABASE_URL'] ?? null);

        if (!$url) {
            $this->markTestSkipped('TEST_DATABASE_URL is not set.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2) . '/Fixtures/Doctrine/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            new DsnParser(['mysql' => 'pdo_mysql'])->parse($url),
            $config
        );

        $this->entityManager = new EntityManager($connection, $config);
        $this->schemaTool = new SchemaTool($this->entityManager);
        $this->metadata = [
            $this->entityManager->getClassMetadata(LimitParent::class),
            $this->entityManager->getClassMetadata(LimitChild::class),
        ];

        $this->schemaTool->dropSchema($this->metadata);
        $this->schemaTool->createSchema($this->metadata);

        $childId = 0;

        for ($id = 1; $id <= self::PARENTS; $id++) {
            $parent = new LimitParent($id);

            for ($i = 0; $i < self::CHILDREN_PER_PARENT; $i++) {
                new LimitChild(++$childId, $parent);
            }

            $this->entityManager->persist($parent);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->schemaTool)) {
            $this->schemaTool->dropSchema($this->metadata);
            $this->entityManager->getConnection()->close();
        }
    }

    public function testToManyFetchJoinLimitsTheRootEntities(): void
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $extension = new UnpaginatedCollectionLimitExtension(
            new Pagination(['client_enabled' => true, 'maximum_items_per_page' => self::MAX_ITEMS]),
            $registry,
            $requestStack
        );

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p', 'c')
            ->from(LimitParent::class, 'p')
            ->leftJoin('p.children', 'c')
            ->orderBy('p.id');

        /** @var LimitParent[] $parents */
        $parents = $extension->getResult($queryBuilder, LimitParent::class, new GetCollection(class: LimitParent::class));

        // A SQL LIMIT of MAX_ITEMS + 1 rows would return 1 parent with 3 children
        $this->assertSame([1, 2], array_map(static fn(LimitParent $parent): int => $parent->getId(), $parents));

        foreach ($parents as $parent) {
            $this->assertInstanceOf(PersistentCollection::class, $parent->getChildren());
            $this->assertTrue($parent->getChildren()->isInitialized()); // fetch-joined, not lazy-loaded
            $this->assertCount(self::CHILDREN_PER_PARENT, $parent->getChildren());
        }

        $this->assertSame(self::MAX_ITEMS, $request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }
}
