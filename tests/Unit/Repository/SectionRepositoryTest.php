<?php

namespace App\Tests\Unit\Repository;

use App\Entity\Section;
use App\Repository\SectionRepository;
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
        $this->assertStringContainsString('SELECT * FROM', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('section', $result);
        $this->assertStringContainsString('editor', $result);
        $this->assertStringContainsString('active', $result);
    }

    public function testAssignedSectionsQueryWithArrayUid(): void
    {
        $rvId = 789;
        $uid = [100, 200, 300];

        $result = $this->repository->assignedSectionsQuery($rvId, $uid);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = 789', $result);
        $this->assertStringContainsString('UID IN (100,200,300)', $result);
        $this->assertStringContainsString('SELECT * FROM', $result);
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
        $this->assertStringContainsString('ITEMID` = 456', $result);
        $this->assertStringContainsString('SELECT uuid, CIV', $result);
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
        $this->assertStringContainsString('ITEMID` = 777', $result);
        $this->assertStringContainsString('SELECT uuid, CIV', $result);
    }
}