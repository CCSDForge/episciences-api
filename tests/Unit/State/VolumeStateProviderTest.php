<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Entity\Paper;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use App\State\VolumeStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Unit tests for VolumeStateProvider::provide().
 *
 * Covers:
 * - Returns null when operation class is not Volume
 * - Returns null for item operation with no vid in uriVariables
 * - Returns findOneByWithContext result for item operation with vid
 * - Collection + pagination disabled → listQuery result
 * - Collection + pagination enabled → Paginator wrapping listPaginator
 * - isGranted flag from Security propagated to filters
 */
final class VolumeStateProviderTest extends TestCase
{
    private MockObject|EntityManagerInterface $em;
    private MockObject|Security $security;
    private VolumeStateProvider $provider;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->provider = new VolumeStateProvider(
            $this->em,
            $this->createStub(LoggerInterface::class),
            new Pagination(),
            $this->security
        );
    }

    // ── Non-Volume class ──────────────────────────────────────────────────────

    public function testProvideReturnsNullWhenClassIsNotVolume(): void
    {
        $operation = $this->createMock(Operation::class);
        $operation->method('getClass')->willReturn(Paper::class);

        $this->em->expects($this->never())->method('getRepository');

        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertNull($result);
    }

    // ── Item operation (Get) ──────────────────────────────────────────────────

    public function testProvideReturnsNullWhenVidMissingForItemOperation(): void
    {
        $operation = $this->makeGetOperation();

        $this->security->method('isGranted')->willReturn(false);

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->expects($this->never())->method('findOneByWithContext');
        $this->em->method('getRepository')->willReturn($volumeRepo);

        // No 'vid' in uriVariables → returns null
        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertNull($result);
    }

    public function testProvideCallsFindOneByWithContextForItemOperationWithVid(): void
    {
        $operation = $this->makeGetOperation();
        $volume = new Volume();

        $this->security->method('isGranted')->willReturn(false);

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->expects($this->once())
            ->method('findOneByWithContext')
            ->with(['vid' => 7])
            ->willReturn($volume);

        $this->em->method('getRepository')->willReturn($volumeRepo);

        $result = $this->provider->provide($operation, ['vid' => 7], ['filters' => []]);

        $this->assertSame($volume, $result);
    }

    public function testProvideReturnsNullWhenFindOneByWithContextReturnsNull(): void
    {
        $operation = $this->makeGetOperation();

        $this->security->method('isGranted')->willReturn(false);

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->method('findOneByWithContext')->willReturn(null);
        $this->em->method('getRepository')->willReturn($volumeRepo);

        $result = $this->provider->provide($operation, ['vid' => 999], ['filters' => []]);

        $this->assertNull($result);
    }

    // ── Collection operation — pagination disabled ────────────────────────────

    public function testProvideReturnsListQueryResultWhenPaginationDisabled(): void
    {
        $operation = $this->makeGetCollectionOperation(paginationEnabled: false);

        $this->security->method('isGranted')->willReturn(false);

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getResult')->willReturn(['vol1', 'vol2']);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->expects($this->once())->method('listQuery')->willReturn($qb);
        $volumeRepo->expects($this->never())->method('listPaginator');

        $this->em->method('getRepository')->willReturn($volumeRepo);

        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertSame(['vol1', 'vol2'], $result);
    }

    // ── Collection operation — pagination enabled ─────────────────────────────

    public function testProvideCallsListPaginatorWhenPaginationEnabled(): void
    {
        $operation = $this->makeGetCollectionOperation(paginationEnabled: true);

        $this->security->method('isGranted')->willReturn(false);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getFirstResult')->willReturn(0);
        $query->method('getMaxResults')->willReturn(30);

        $doctrinePaginator = $this->createMock(\Doctrine\ORM\Tools\Pagination\Paginator::class);
        $doctrinePaginator->method('getQuery')->willReturn($query);
        $doctrinePaginator->method('getIterator')->willReturn(new \ArrayIterator([]));
        $doctrinePaginator->method('count')->willReturn(0);

        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->expects($this->once())->method('listPaginator')->willReturn($doctrinePaginator);
        $volumeRepo->expects($this->never())->method('listQuery');

        $this->em->method('getRepository')->willReturn($volumeRepo);

        $result = $this->provider->provide($operation, [], ['filters' => []]);

        $this->assertInstanceOf(\ApiPlatform\Doctrine\Orm\Paginator::class, $result);
    }

    // ── isGranted propagation ─────────────────────────────────────────────────

    public function testIsGrantedTrueFromSecurityPropagatedToFilters(): void
    {
        $operation = $this->makeGetOperation();

        $this->security->method('isGranted')->with('ROLE_SECRETARY')->willReturn(true);

        $capturedFilters = null;
        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->method('findOneByWithContext')
            ->willReturnCallback(function (array $criteria, mixed $ignored, array $filters) use (&$capturedFilters): null {
                $capturedFilters = $filters;
                return null;
            });

        $this->em->method('getRepository')->willReturn($volumeRepo);

        $this->provider->provide($operation, ['vid' => 1], ['filters' => []]);

        $this->assertIsArray($capturedFilters);
        $this->assertTrue($capturedFilters['isGranted']);
    }

    public function testIsGrantedFalseFromSecurityPropagatedToFilters(): void
    {
        $operation = $this->makeGetOperation();

        $this->security->method('isGranted')->with('ROLE_SECRETARY')->willReturn(false);

        $capturedFilters = null;
        $volumeRepo = $this->createMock(VolumeRepository::class);
        $volumeRepo->method('findOneByWithContext')
            ->willReturnCallback(function (array $criteria, mixed $ignored, array $filters) use (&$capturedFilters): null {
                $capturedFilters = $filters;
                return null;
            });

        $this->em->method('getRepository')->willReturn($volumeRepo);

        $this->provider->provide($operation, ['vid' => 1], ['filters' => []]);

        $this->assertIsArray($capturedFilters);
        $this->assertFalse($capturedFilters['isGranted']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeGetOperation(): Get
    {
        return (new Get(paginationEnabled: false))->withClass(Volume::class);
    }

    private function makeGetCollectionOperation(bool $paginationEnabled = false): GetCollection
    {
        return (new GetCollection(paginationEnabled: $paginationEnabled))->withClass(Volume::class);
    }
}
