<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\PaperConflicts;
use App\Repository\PaperConflictsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PaperConflictsRepository::save() and remove().
 *
 * Covers:
 * - save() with flush=false: persist called, flush NOT called
 * - save() with flush=true: persist AND flush called
 * - remove() with flush=false: remove called, flush NOT called
 * - remove() with flush=true: remove AND flush called
 */
final class PaperConflictsRepositoryTest extends TestCase
{
    private function makeRepo(): array
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManager')->willReturn($em);

        $repo = $this->getMockBuilder(PaperConflictsRepository::class)
            ->setConstructorArgs([$registry])
            ->onlyMethods(['getEntityManager'])
            ->getMock();

        $repo->method('getEntityManager')->willReturn($em);

        return [$repo, $em];
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSaveCallsPersistWithoutFlush(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('persist')->with($entity);
        $em->expects($this->never())->method('flush');

        $repo->save($entity, false);
    }

    public function testSaveCallsPersistAndFlushWhenFlushIsTrue(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('persist')->with($entity);
        $em->expects($this->once())->method('flush');

        $repo->save($entity, true);
    }

    public function testSaveDefaultFlushIsFalse(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('persist');
        $em->expects($this->never())->method('flush');

        $repo->save($entity);
    }

    // ── remove() ──────────────────────────────────────────────────────────────

    public function testRemoveCallsEmRemoveWithoutFlush(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('remove')->with($entity);
        $em->expects($this->never())->method('flush');

        $repo->remove($entity, false);
    }

    public function testRemoveCallsEmRemoveAndFlushWhenFlushIsTrue(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('remove')->with($entity);
        $em->expects($this->once())->method('flush');

        $repo->remove($entity, true);
    }

    public function testRemoveDefaultFlushIsFalse(): void
    {
        [$repo, $em] = $this->makeRepo();
        $entity = new PaperConflicts();

        $em->expects($this->once())->method('remove');
        $em->expects($this->never())->method('flush');

        $repo->remove($entity);
    }
}
