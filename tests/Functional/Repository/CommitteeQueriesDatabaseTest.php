<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Paper;
use App\Entity\Section;
use App\Entity\User;
use App\Entity\UserAssignment;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use App\Repository\SectionRepository;
use App\Repository\VolumePaperRepository;
use App\Repository\VolumeRepository;
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
 * The editors of the sections and of the volumes (committees) and the sections assigned to the editors:
 * the same results as the reference SQL queries they replaced (non-regression), against a real MySQL database.
 * The reference queries compare the date of the latest assignment by editor (the queries they replaced compared it
 * with the latest assignment of any editor of the item, so that an unassigned editor could still be listed).
 *
 * Requires TEST_DATABASE_URL (set in CI, see docker-compose.ci.yml); skipped otherwise.
 * The test creates and drops the USER_ASSIGNMENT, SECTION, VOLUME, USER and PAPERS tables: it is skipped if one of them
 * already exists, so that it never touches real data.
 */
class CommitteeQueriesDatabaseTest extends TestCase
{
    private const int RVID = 1;
    private const int OTHER_RVID = 2;

    private EntityManagerInterface $entityManager;
    private SectionRepository $sectionRepository;
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

        foreach (['USER_ASSIGNMENT', Section::TABLE, Volume::TABLE, User::TABLE, Paper::TABLE] as $table) {
            if ($connection->createSchemaManager()->tablesExist([$table])) {
                $connection->close();
                $this->markTestSkipped(sprintf('The %s table already exists: the test does not touch existing data.', $table));
            }
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->metadata = array_map(
            $this->entityManager->getClassMetadata(...),
            [UserAssignment::class, Section::class, Volume::class, User::class, Paper::class]
        );

        // Only these tables are created: their foreign keys reference tables that do not exist
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        new SchemaTool($this->entityManager)->createSchema($this->metadata);
        $this->tablesCreated = true;

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->sectionRepository = new SectionRepository($registry);
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

    public function testSectionCommitteesMatchTheReferenceQuery(): void
    {
        foreach ([self::RVID, self::OTHER_RVID] as $rvId) {
            foreach ([10, 11, 12, 30, 99] as $sid) {
                $this->assertSame(
                    $this->fetch(self::referenceCommitteeSql($rvId, $sid, 'section', Section::TABLE, 'SID')),
                    $this->sectionRepository->getCommittee($rvId, $sid),
                    sprintf('journal %d, section %d', $rvId, $sid)
                );
            }
        }
    }

    public function testVolumeCommitteesMatchTheReferenceQuery(): void
    {
        foreach ([self::RVID, self::OTHER_RVID] as $rvId) {
            foreach ([10, 20, 21, 99] as $vid) {
                $this->assertSame(
                    $this->fetch(self::referenceCommitteeSql($rvId, $vid, 'volume', Volume::TABLE, 'VID')),
                    $this->volumeRepository->getCommittee($rvId, $vid),
                    sprintf('journal %d, volume %d', $rvId, $vid)
                );
            }
        }
    }

    /**
     * The editors of the section 10: Adams and Clark.
     * Brown's latest assignment is inactive; Davis's latest assignment is inactive too: his earlier active assignment,
     * which has the date of Brown's latest assignment, is left out (the date is compared by editor).
     */
    public function testSectionCommittee(): void
    {
        $this->assertSame(['Adams', 'Clark'], array_column($this->sectionRepository->getCommittee(self::RVID, 10), 'screenName'));
        // a removed section has no committee
        $this->assertSame([], $this->sectionRepository->getCommittee(self::RVID, 12));
    }

    public function testAssignedSectionsMatchTheReferenceQuery(): void
    {
        foreach ([[1, 2, 3, 4], [1], [4], [5], [2, 7], [6]] as $uids) {
            $expected = [];

            foreach ($this->fetch(self::referenceAssignedSectionsSql(self::RVID, $uids)) as $row) {
                if ($row['SID'] !== null) {
                    $expected[(int)$row['UID']][] = [(int)$row['SID'], (int)$row['RVID'], (int)$row['POSITION'], json_decode((string)$row['titles'], true), $row['descriptions'] === null ? null : json_decode((string)$row['descriptions'], true), (int)$row['totalPublishedArticles']];
                }
            }

            $actual = array_map(
                static fn(array $sections): array => array_map(
                    static fn(Section $section): array => [$section->getSid(), $section->getRvid(), $section->getPosition(), $section->getTitles(), $section->getDescriptions(), $section->getTotalPublishedArticles()],
                    $sections
                ),
                $this->sectionRepository->getAssignedSection(self::RVID, $uids)
            );

            ksort($expected);
            ksort($actual);
            $this->assertSame($expected, $actual, 'editors ' . implode(',', $uids));
        }
    }

    public function testAssignedSections(): void
    {
        $sections = $this->sectionRepository->getAssignedSection(self::RVID, [1, 7]);

        // by position: section 11, then section 10 (2 published papers)
        $this->assertSame([11, 10], array_map(static fn(Section $section): int => $section->getSid(), $sections[1]));
        $this->assertSame(2, $sections[1][1]->getTotalPublishedArticles());
        $this->assertSame(['en' => 'Section A'], $sections[1][1]->getTitles());
        $this->assertSame([11], array_map(static fn(Section $section): int => $section->getSid(), $sections[7]));
        // Davis's latest assignment to the section 10 is inactive, whatever the other editors requested
        $this->assertArrayNotHasKey(4, $this->sectionRepository->getAssignedSection(self::RVID, [1, 2, 3, 4]));
        // no editor: no section (the reference query failed)
        $this->assertSame([], $this->sectionRepository->getAssignedSection(self::RVID, []));
    }

    /**
     * Reference: the SQL query of a committee, before its conversion to Doctrine, with the date of the latest
     * assignment compared by editor (r1.UID).
     */
    private static function referenceCommitteeSql(int $rvId, int $itemId, string $item, string $table, string $idColumn): string
    {
        return "SELECT CIV as `civ`, SCREEN_NAME AS `screenName`, ORCID AS `orcid` FROM ( SELECT UID, $idColumn, `WHEN` FROM ( SELECT `ua`.* FROM `USER_ASSIGNMENT` AS `ua` INNER JOIN( SELECT `USER_ASSIGNMENT`.`ITEMID`, UID, MAX(`WHEN`) AS `WHEN` FROM `USER_ASSIGNMENT` WHERE (RVID = $rvId) AND (`USER_ASSIGNMENT`.`ITEMID` = $itemId) AND(ITEM = '$item') AND(ROLEID = 'editor') GROUP BY `ITEMID`, UID ) AS `r1` ON ua.ITEMID = r1.ITEMID AND ua.UID = r1.UID AND ua.`WHEN` = r1.`WHEN` WHERE (RVID = $rvId) AND (ua.ITEMID = $itemId) AND (ITEM = '$item') AND(ROLEID = 'editor') AND( STATUS = 'active' ) ) AS `r2` INNER JOIN $table AS s ON s.RVID = r2.RVID AND s.$idColumn = r2.ITEMID) AS `result` INNER JOIN USER AS `u` ON result.UID = u.UID GROUP BY $idColumn, u.UID, u.CIV, u.SCREEN_NAME, u.ORCID, u.LASTNAME ORDER BY u.LASTNAME ASC";
    }

    /**
     * Reference: the SQL query of the sections assigned to editors, before its conversion to Doctrine, with the date
     * of the latest assignment compared by editor (r1.UID).
     *
     * @param int[] $uids
     */
    private static function referenceAssignedSectionsSql(int $rvId, array $uids): string
    {
        $implodedUids = implode(',', $uids);

        return "SELECT DISTINCT(ua.UID), ua.RVID, sc.SID, sc.titles, sc.descriptions, sc.POSITION,
            (SELECT COUNT(*) FROM PAPERS AS p WHERE p.RVID = sc.RVID AND p.SID = sc.SID AND p.STATUS = " . Paper::STATUS_PUBLISHED . ") AS totalPublishedArticles
            FROM USER_ASSIGNMENT AS ua
            JOIN (SELECT ITEMID, UID, MAX(`WHEN`) AS max_when FROM USER_ASSIGNMENT
                WHERE RVID = $rvId AND ITEM = 'section' AND ROLEID = 'editor' AND UID IN ($implodedUids) GROUP BY ITEMID, UID) AS r1
            ON ua.ITEMID = r1.ITEMID AND ua.UID = r1.UID AND ua.`WHEN` = r1.max_when
            LEFT JOIN SECTION AS sc ON sc.RVID = ua.RVID AND sc.SID = ua.ITEMID
            WHERE ua.RVID = $rvId AND ua.ITEM = 'section' AND ua.ROLEID = 'editor' AND ua.STATUS = 'active' AND ua.UID IN ($implodedUids)
            ORDER BY sc.POSITION ASC";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(string $sql): array
    {
        return $this->entityManager->getConnection()->executeQuery($sql)->fetchAllAssociative();
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

        foreach ([[self::RVID, 10, 2, '{"en": "Section A"}'], [self::RVID, 11, 1, '{"en": "Section B"}'], [self::OTHER_RVID, 30, 1, '{"en": "Section C"}']] as [$rvId, $sid, $position, $titles]) {
            $insert(Section::TABLE, ['RVID' => $rvId, 'SID' => $sid, 'POSITION' => $position, 'titles' => $titles, 'descriptions' => null]);
        }

        foreach ([20, 21] as $vid) {
            $insert(Volume::TABLE, ['RVID' => self::RVID, 'VID' => $vid]);
        }

        foreach ([1 => 'Adams', 2 => 'Brown', 3 => 'Clark', 4 => 'Davis', 5 => 'Evans', 6 => 'Ford', 7 => 'Green'] as $uid => $lastname) {
            $insert(User::TABLE, ['UID' => $uid, 'uuid' => sprintf('00000000-0000-0000-0000-%012d', $uid), 'USERNAME' => 'user' . $uid, 'EMAIL' => "user$uid@example.org", 'LASTNAME' => $lastname, 'SCREEN_NAME' => $lastname, 'CIV' => $uid % 2 ? 'M.' : null, 'ORCID' => $uid % 3 ? null : '0000-0000-0000-000' . $uid]);
        }

        $assignments = [
            // rvid, item id, item, uid, role, status, date
            [self::RVID, 10, 'section', 1, 'editor', 'active', '2024-01-01 10:00:00'],
            [self::RVID, 10, 'section', 2, 'editor', 'active', '2024-01-01 11:00:00'],
            [self::RVID, 10, 'section', 2, 'editor', 'inactive', '2024-02-01 10:00:00'], // latest assignment: inactive
            [self::RVID, 10, 'section', 3, 'editor', 'inactive', '2024-01-01 10:00:00'],
            [self::RVID, 10, 'section', 3, 'editor', 'active', '2024-03-01 10:00:00'],   // active again
            [self::RVID, 10, 'section', 4, 'editor', 'active', '2024-02-01 10:00:00'],   // the date of the latest assignment of the editor 2
            [self::RVID, 10, 'section', 4, 'editor', 'inactive', '2024-04-01 10:00:00'],
            [self::RVID, 10, 'section', 5, 'guest_editor', 'active', '2024-01-01 10:00:00'], // not an editor
            [self::RVID, 10, 'volume', 6, 'editor', 'active', '2024-01-01 10:00:00'],    // volume 10: does not exist
            [self::RVID, 11, 'section', 1, 'editor', 'active', '2024-01-05 10:00:00'],
            [self::RVID, 11, 'section', 7, 'editor', 'active', '2024-01-06 10:00:00'],
            [self::RVID, 12, 'section', 2, 'editor', 'active', '2024-05-05 10:00:00'],   // removed section
            [self::OTHER_RVID, 30, 'section', 1, 'editor', 'active', '2024-01-01 10:00:00'],
            [self::RVID, 20, 'volume', 1, 'editor', 'active', '2024-01-01 10:00:00'],
            [self::RVID, 20, 'volume', 3, 'editor', 'active', '2024-01-01 10:00:00'],
            [self::RVID, 20, 'volume', 3, 'editor', 'inactive', '2024-06-01 10:00:00'],
            [self::RVID, 21, 'volume', 2, 'editor', 'active', '2024-01-01 10:00:00'],
        ];

        foreach ($assignments as [$rvId, $itemId, $item, $uid, $role, $status, $date]) {
            $insert('USER_ASSIGNMENT', ['RVID' => $rvId, 'ITEMID' => $itemId, 'ITEM' => $item, 'UID' => $uid, 'ROLEID' => $role, 'STATUS' => $status, 'WHEN' => $date]);
        }

        foreach ([[1, 10, Paper::STATUS_PUBLISHED], [2, 10, Paper::STATUS_PUBLISHED], [3, 10, Paper::STATUS_SUBMITTED], [4, 30, Paper::STATUS_PUBLISHED]] as [$docId, $sid, $status]) {
            $insert(Paper::TABLE, ['DOCID' => $docId, 'PAPERID' => $docId, 'RVID' => $sid === 30 ? self::OTHER_RVID : self::RVID, 'SID' => $sid, 'STATUS' => $status]);
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
