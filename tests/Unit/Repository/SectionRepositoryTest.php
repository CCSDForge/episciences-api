<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\SectionRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class SectionRepositoryTest extends TestCase
{
    private SectionRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new SectionRepository($this->createStub(ManagerRegistry::class));
    }

    // ── assignedSectionsQuery ────────────────────────────────────────────────

    public function testAssignedSectionsQueryIsParameterized(): void
    {
        $result = $this->repository->assignedSectionsQuery([123, 456]);

        $this->assertIsString($result);
        // Must use ? placeholders — no literal UID or RVID values
        $this->assertStringNotContainsString('123', $result);
        $this->assertStringNotContainsString('456', $result);
        $this->assertStringContainsString('RVID = ?', $result);
        $this->assertStringContainsString('UID IN(?,?)', $result);
    }

    public function testGetAssignedSectionWithIntUid(): void
    {
        $result = $this->repository->assignedSectionsQuery(42);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = ?', $result);
        $this->assertStringContainsString('UID IN(?)', $result);
        $this->assertStringNotContainsString('42', $result);
    }

    public function testAssignedSectionsQueryWithArrayUid(): void
    {
        $result = $this->repository->assignedSectionsQuery([100, 200, 300]);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = ?', $result);
        $this->assertStringContainsString('UID IN(?,?,?)', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('section', $result);
        $this->assertStringContainsString('editor', $result);
        $this->assertStringContainsString('active', $result);
    }

    public function testAssignedSectionsQueryWithEmptyArray(): void
    {
        $result = $this->repository->assignedSectionsQuery([]);

        $this->assertIsString($result);
        $this->assertStringContainsString('RVID = ?', $result);
        $this->assertStringContainsString('UID IN()', $result);
    }

    public function testAssignedSectionsQueryContainsExpectedClauses(): void
    {
        $result = $this->repository->assignedSectionsQuery([1]);

        $this->assertStringContainsString('SELECT DISTINCT(ua.UID)', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('ORDER BY sc.POSITION ASC', $result);
    }

    // ── getCommitteeQuery ────────────────────────────────────────────────────

    public function testGetCommitteeQueryIsParameterized(): void
    {
        $result = $this->repository->getCommitteeQuery();

        $this->assertIsString($result);
        $this->assertStringNotContainsString('= 123', $result);
        $this->assertStringNotContainsString('= 456', $result);
        // Four ? placeholders: rvId, sid, rvId, sid
        $this->assertSame(4, substr_count($result, '?'));
    }

    public function testGetCommitteeQueryContainsExpectedClauses(): void
    {
        $result = $this->repository->getCommitteeQuery();

        $this->assertStringContainsString('SELECT uuid, CIV', $result);
        $this->assertStringContainsString('SCREEN_NAME', $result);
        $this->assertStringContainsString('ORCID', $result);
        $this->assertStringContainsString('USER_ASSIGNMENT', $result);
        $this->assertStringContainsString('section', $result);
        $this->assertStringContainsString('editor', $result);
        $this->assertStringContainsString('active', $result);
        $this->assertStringContainsString('ORDER BY u.LASTNAME ASC', $result);
    }

    // ── getCommitteeReturnEmptyOnException ───────────────────────────────────

    public function testGetCommitteeThrowsOnDbalException(): void
    {
        // getCommittee() now propagates DBAL exceptions rather than silencing them
        $this->expectException(\Doctrine\DBAL\Exception::class);

        $conn = $this->createMock(\Doctrine\DBAL\Connection::class);
        $conn->method('executeQuery')->willThrowException(
            $this->createStub(\Doctrine\DBAL\Exception::class)
        );

        $metadata = $this->createStub(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('getConnection')->willReturn($conn);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new SectionRepository($registry);
        $repo->getCommittee(1, 2);
    }
}
