<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\VolumePaper;
use App\Entity\VolumePaperPosition;
use App\Repository\PapersRepository;
use App\Repository\VolumePaperRepository;
use App\Repository\VolumeRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The sorted papers of the volumes: the same papers, in the same order, as the reference SQL query it replaced
 * (non-regression), against a real MySQL database.
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the PAPERS, VOLUME_PAPER and VOLUME_PAPER_POSITION tables: it is skipped if one of them
 * already exists, so that it never touches real data.
 */
class VolumePapersDatabaseTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private VolumeRepository $volumeRepository;

    /** @var list<ClassMetadata<object>> */
    private array $metadata = [];

    private bool $tablesCreated = false;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL') ?: ($_SERVER['TEST_DATABASE_URL'] ?? null);

        if (!$url) {
            $this->markTestSkipped('TEST_DATABASE_URL is not set.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url), $config);

        foreach ([Paper::TABLE, 'VOLUME_PAPER', 'VOLUME_PAPER_POSITION'] as $table) {
            if ($connection->createSchemaManager()->tablesExist([$table])) {
                $connection->close();
                $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', $table));
            }
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = array_map($this->entityManager->getClassMetadata(...), [Paper::class, VolumePaper::class, VolumePaperPosition::class]);

        // Only these tables are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tablesCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->volumeRepository = new VolumeRepository($registry, $this->createStub(PapersRepository::class), $this->createStub(VolumePaperRepository::class), new NullLogger());

        $this->insertData();
    }

    protected function tearDown(): void
    {
        if ($this->tablesCreated) {
            new SchemaTool($this->entityManager)->dropSchema($this->metadata);
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testSortedPapersMatchTheReferenceQuery(): void
    {
        foreach ([[1], [2], [1, 2], [2, 1, 1], [3], [1, 2, 3]] as $vids) {
            $this->assertSame(
                $this->referenceSortedPapers($vids),
                self::describe($this->volumeRepository->fetchSortedPapersByVolumes($vids)),
                'volumes ' . implode(',', $vids)
            );
        }
    }

    public function testSortedPapers(): void
    {
        $papers = self::describe($this->volumeRepository->fetchSortedPapersByVolumes([1]));

        // by position (the papers without position last), the latest version of the paper 5
        $this->assertSame([[2, 2, 16, 1], [6, 5, 16, null]], $papers[1]['publicCollection']);
        $this->assertSame([[1, 1, 0, 2], [4, 4, 0, null]], $papers[1]['privateCollection']);
    }

    /**
     * Reference: the SQL query of the sorted papers, before its conversion to Doctrine, then the same processing
     * (the first version of each paper, published or not).
     *
     * @param int[] $vids
     * @return array<int, array{privateCollection: list<array>, publicCollection: list<array>}>
     */
    private function referenceSortedPapers(array $vids): array
    {
        $vids = array_values(array_unique($vids));
        $sql = 'SELECT t.VID, t.DOCID, t.PAPERID, t.STATUS, t.POSITION FROM (
                SELECT p.VID AS VID, p.DOCID, p.PAPERID, p.STATUS, vpp.POSITION FROM PAPERS p
                LEFT JOIN VOLUME_PAPER_POSITION vpp ON (vpp.VID = p.VID AND vpp.PAPERID = p.PAPERID)
                WHERE p.VID IN (:vids) AND p.STATUS != :obsolete
                UNION ALL
                SELECT vp.VID AS VID, p.DOCID, p.PAPERID, p.STATUS, vpp.POSITION FROM VOLUME_PAPER vp
                INNER JOIN PAPERS p ON (p.DOCID = vp.DOCID)
                LEFT JOIN VOLUME_PAPER_POSITION vpp ON (vpp.VID = vp.VID AND vpp.PAPERID = p.PAPERID)
                WHERE vp.VID IN (:vids) AND p.STATUS != :obsolete
            ) t
            ORDER BY t.VID ASC, COALESCE(t.POSITION, 999999) ASC, t.PAPERID ASC, t.DOCID DESC';

        $rows = $this->entityManager->getConnection()->executeQuery($sql, ['vids' => $vids, 'obsolete' => Paper::STATUS_OBSOLETE], ['vids' => ArrayParameterType::INTEGER])->fetchAllAssociative();

        $papers = array_fill_keys($vids, ['privateCollection' => [], 'publicCollection' => []]);
        $processed = [];

        foreach ($rows as $row) {
            [$vid, $paperId] = [(int)$row['VID'], (int)$row['PAPERID']];

            if (isset($processed[$vid][$paperId])) {
                continue;
            }

            $processed[$vid][$paperId] = true;
            $collection = (int)$row['STATUS'] === Paper::STATUS_PUBLISHED ? 'publicCollection' : 'privateCollection';
            $papers[$vid][$collection][] = [(int)$row['DOCID'], $paperId, (int)$row['STATUS'], $row['POSITION'] === null ? null : (int)$row['POSITION']];
        }

        return $papers;
    }

    /**
     * @param array<int, array{privateCollection: iterable<Paper>, publicCollection: iterable<Paper>}> $sorted
     * @return array<int, array{privateCollection: list<array>, publicCollection: list<array>}>
     */
    private static function describe(array $sorted): array
    {
        $describe = static fn(iterable $papers): array => array_map(
            static fn(Paper $paper): array => [$paper->getDocid(), $paper->getPaperid(), $paper->getStatus(), $paper->getPaperPosition()],
            [...$papers]
        );

        return array_map(static fn(array $collections): array => [
            'privateCollection' => $describe($collections['privateCollection']),
            'publicCollection' => $describe($collections['publicCollection']),
        ], $sorted);
    }

    private function insertData(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        // the reserved words (WHEN) are quoted
        $insert = function (string $table, array $values) use ($connection, $platform): void {
            $row = array_merge($this->requiredColumnDefaults($table), $values);
            $connection->insert($table, array_combine(array_map($platform->quoteSingleIdentifier(...), array_keys($row)), $row));
        };

        $papers = [
            // docid, paperid, vid (of the paper), status
            [1, 1, 1, Paper::STATUS_SUBMITTED],
            [2, 2, 1, Paper::STATUS_PUBLISHED],
            [3, 3, 1, Paper::STATUS_OBSOLETE],      // obsolete: left out
            [4, 4, 1, Paper::STATUS_SUBMITTED],     // without position
            [5, 5, 1, Paper::STATUS_OBSOLETE],      // previous version of the paper 5
            [6, 5, 1, Paper::STATUS_PUBLISHED],     // latest version of the paper 5, without position
            [7, 7, 2, Paper::STATUS_PUBLISHED],
            [8, 8, 0, Paper::STATUS_PUBLISHED],     // only attached to the volume 2 (VOLUME_PAPER)
            [9, 9, 0, Paper::STATUS_SUBMITTED],     // attached to the volume 2, two versions
            [10, 9, 0, Paper::STATUS_SUBMITTED],
        ];

        foreach ($papers as [$docId, $paperId, $vid, $status]) {
            $insert(Paper::TABLE, ['DOCID' => $docId, 'PAPERID' => $paperId, 'RVID' => 1, 'VID' => $vid, 'STATUS' => $status]);
        }

        // vid, docid: papers attached to a volume which is not theirs; the paper 7 is in its volume and attached to it
        foreach ([[2, 2], [2, 8], [2, 9], [2, 10], [2, 7], [2, 3]] as $i => [$vid, $docId]) {
            $insert('VOLUME_PAPER', ['ID' => $i + 1, 'VID' => $vid, 'DOCID' => $docId]);
        }

        // vid, paperid, position
        foreach ([[1, 1, 2], [1, 2, 1], [2, 8, 1], [2, 2, 2], [2, 7, 3], [2, 3, 4]] as [$vid, $paperId, $position]) {
            $insert('VOLUME_PAPER_POSITION', ['VID' => $vid, 'PAPERID' => $paperId, 'POSITION' => $position]);
        }
    }

    /**
     * A value for each NOT NULL column without default, so that only the columns
     * read by the tested query need to be set.
     *
     * @return array<string, int|string>
     */
    private function requiredColumnDefaults(string $table): array
    {
        $defaults = [];
        foreach ($this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName($table)->getColumns() as $column) {
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
