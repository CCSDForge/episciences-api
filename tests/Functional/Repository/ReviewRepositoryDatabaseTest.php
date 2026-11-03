<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * A disabled journal is not found, as an unknown one: every journal code of the API (rvcode, /journals/stats/{code})
 * is resolved by ReviewRepository::getJournalByIdentifier(), so a disabled journal returns a 404.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the REVIEW and REVIEW_SETTING tables (the settings are loaded with the journal):
 * it is skipped if one of them already exists,
 * so that it never touches real data.
 */
class ReviewRepositoryDatabaseTest extends TestCase
{
    private const int ACTIVE_RVID = 1;
    private const int DISABLED_RVID = 2;

    private EntityManagerInterface $entityManager;
    private ReviewRepository $repository;

    /** @var list<ClassMetadata<object>> */
    private array $metadata = [];

    private bool $tableCreated = false;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL') ?: ($_SERVER['TEST_DATABASE_URL'] ?? null);

        if (!$url) {
            $this->markTestSkipped('TEST_DATABASE_URL is not set.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $config);

        foreach ([Review::TABLE, ReviewSetting::TABLE] as $table) {
            if ($connection->createSchemaManager()->tablesExist([$table])) {
                $connection->close();
                $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', $table));
            }
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = [$this->entityManager->getClassMetadata(Review::class), $this->entityManager->getClassMetadata(ReviewSetting::class)];

        // Only REVIEW and REVIEW_SETTING are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tableCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new ReviewRepository($registry);

        $defaults = $this->requiredColumnDefaults();

        foreach ([self::ACTIVE_RVID => ['active', Review::STATUS_ENABLED], self::DISABLED_RVID => ['disabled', Review::STATUS_DISABLED]] as $rvId => [$code, $status]) {
            $connection->insert(Review::TABLE, array_merge($defaults, ['RVID' => $rvId, 'CODE' => $code, 'STATUS' => $status]));
        }
    }

    protected function tearDown(): void
    {
        if ($this->tableCreated) {
            new SchemaTool($this->entityManager)->dropSchema($this->metadata);
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testActiveJournalIsFound(): void
    {
        $this->assertSame(self::ACTIVE_RVID, $this->repository->getJournalByIdentifier('active')?->getRvid());
        $this->assertSame('active', $this->repository->getJournalByIdentifier(self::ACTIVE_RVID)?->getCode());
    }

    public function testDisabledJournalIsNotFoundAsAnUnknownOne(): void
    {
        $this->assertNull($this->repository->getJournalByIdentifier('disabled'));
        $this->assertNull($this->repository->getJournalByIdentifier(self::DISABLED_RVID));
        $this->assertNull($this->repository->getJournalByIdentifier('unknown'));
    }

    public function testDisabledJournalIsOnlyFoundWhenNotStrict(): void
    {
        $this->assertSame(self::DISABLED_RVID, $this->repository->getJournalByIdentifier('disabled', false)?->getRvid());
    }

    /**
     * A value for each NOT NULL column without default, so that only the columns
     * read by the tested query need to be set.
     *
     * @return array<string, int|string>
     */
    private function requiredColumnDefaults(): array
    {
        $defaults = [];
        foreach ($this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName(Review::TABLE)->getColumns() as $column) {
            if (!$column->getNotnull() || $column->getDefault() !== null || $column->getAutoincrement()) {
                continue;
            }

            $type = $column->getType();
            $name = trim($column->getName(), "`\"");

            $defaults[$name] = match (true) {
                $type instanceof \Doctrine\DBAL\Types\IntegerType,
                $type instanceof \Doctrine\DBAL\Types\SmallIntType,
                $type instanceof \Doctrine\DBAL\Types\BigIntType,
                $type instanceof \Doctrine\DBAL\Types\BooleanType => 0,
                $type instanceof \Doctrine\DBAL\Types\DateTimeType,
                $type instanceof \Doctrine\DBAL\Types\DateTimeImmutableType => '2000-01-01 00:00:00',
                $type instanceof \Doctrine\DBAL\Types\DateType => '2000-01-01',
                $type instanceof \Doctrine\DBAL\Types\JsonType => '[]',
                default => '',
            };
        }

        return $defaults;
    }
}
