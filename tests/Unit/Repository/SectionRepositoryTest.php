<?php

namespace App\Tests\Unit\Repository;

use App\Entity\Section;
use App\Repository\SectionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class SectionRepositoryTest extends TestCase
{
    private SectionRepository $repository;

    protected function setUp(): void
    {
        // Keep as pure unit tests - just test the pure functions without Symfony container
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->repository = new SectionRepository($managerRegistry);
    }

    public function testAssignedSectionsQueryWithSingleUid(): void
    {
        $rvId = 123;
        $uid = 456;

        $result = $this->repository->assignedSectionsQuery($rvId, $uid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 123', $result);
        $this->assertStringContainsString('UID IN (456)', $result);
        $this->assertStringContainsString('SELECT DISTINCT(ua.UID)', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('section', $result);
        $this->assertStringContainsString('editor', $result);
        $this->assertStringContainsString('active', $result);
    }

    /**
     * Regression: the assigned sections are built from raw rows, without their papers,
     * so their number of published articles has to be counted by the query.
     */
    public function testAssignedSectionsQueryCountsThePublishedArticles(): void
    {
        $result = $this->repository->assignedSectionsQuery(123, 456);

        $this->assertStringContainsString('SELECT COUNT(*) FROM PAPERS AS p WHERE p.RVID = sc.RVID AND p.SID = sc.SID AND p.STATUS = 16', $result);
        $this->assertStringContainsString('AS totalPublishedArticles', $result);
    }

    public function testGetAssignedSectionSetsTheCountedPublishedArticles(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['UID' => 7, 'RVID' => 3, 'SID' => 4, 'titles' => '{"en":"Section A"}', 'descriptions' => null, 'POSITION' => 2, 'totalPublishedArticles' => '12'],
            ['UID' => 7, 'RVID' => 3, 'SID' => null, 'titles' => null, 'descriptions' => null, 'POSITION' => null, 'totalPublishedArticles' => '0'],
        ]);

        $statement = $this->createStub(Statement::class);
        $statement->method('executeQuery')->willReturn($result);

        $connection = $this->createStub(Connection::class);
        $connection->method('prepare')->willReturn($statement);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(Section::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $sections = (new SectionRepository($registry))->getAssignedSection(3, [7]);

        $this->assertCount(1, $sections[7]);
        $this->assertSame(4, $sections[7][0]->getSid());
        $this->assertSame(['en' => 'Section A'], $sections[7][0]->getTitles());
        $this->assertSame(12, $sections[7][0]->getTotalPublishedArticles());
    }

    public function testAssignedSectionsQueryWithArrayUid(): void
    {
        $rvId = 789;
        $uid = [100, 200, 300];

        $result = $this->repository->assignedSectionsQuery($rvId, $uid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 789', $result);
        $this->assertStringContainsString('UID IN (100,200,300)', $result);
        $this->assertStringContainsString('SELECT DISTINCT(ua.UID)', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
    }

    public function testAssignedSectionsQueryWithEmptyArray(): void
    {
        $rvId = 999;
        $uid = [];

        $result = $this->repository->assignedSectionsQuery($rvId, $uid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 999', $result);
        $this->assertStringContainsString('UID IN ()', $result);
    }

    public function testAssignedSectionsQueryWithNullUid(): void
    {
        $rvId = 111;
        $uid = null;

        $result = $this->repository->assignedSectionsQuery($rvId, $uid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 111', $result);
        $this->assertStringContainsString('UID IN ()', $result);
    }

    public function testGetCommitteeQuery(): void
    {
        $rvId = 123;
        $sid = 456;

        $result = $this->repository->getCommitteeQuery($rvId, $sid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 123', $result);
        $this->assertStringContainsString('ua.ITEMID = 456', $result);
        // The committee is public: no account identifier
        $this->assertStringContainsString('SELECT CIV', $result);
        $this->assertStringNotContainsString('uuid', $result);
        $this->assertStringContainsString('SCREEN_NAME', $result);
        $this->assertStringContainsString('ORCID', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('section', $result);
        $this->assertStringContainsString('editor', $result);
        $this->assertStringContainsString('active', $result);
        $this->assertStringContainsString('ORDER BY u.LASTNAME ASC', $result);
    }

    public function testGetCommitteeQueryWithDifferentParameters(): void
    {
        $rvId = 999;
        $sid = 777;

        $result = $this->repository->getCommitteeQuery($rvId, $sid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 999', $result);
        $this->assertStringContainsString('ua.ITEMID = 777', $result);
        $this->assertStringContainsString('SELECT CIV', $result);
    }
}