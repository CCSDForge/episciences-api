<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\Pagination;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use App\Entity\Volume;
use App\Repository\VolumeRepository;
use App\State\VolumeStateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The volumes requested without pagination are capped like the Doctrine collections.
 * A single volume is only returned for the requested journal.
 * A page lower than 1 is rejected.
 */
class VolumeStateProviderTest extends TestCase
{
    private const int MAX_ITEMS = 2;

    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testUnpaginatedVolumesAboveTheMaximumAreTruncatedAndFlagged(): void
    {
        $volumes = $this->createVolumes(self::MAX_ITEMS + 1);

        $result = $this->provide($volumes);

        $this->assertSame(array_slice($volumes, 0, self::MAX_ITEMS), $result);
        $this->assertSame(self::MAX_ITEMS, $this->request->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testUnpaginatedVolumesWithinTheMaximumAreUnchanged(): void
    {
        $volumes = $this->createVolumes(self::MAX_ITEMS);

        $this->assertSame($volumes, $this->provide($volumes));
        $this->assertFalse($this->request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE));
    }

    public function testVolumeIsSearchedInTheRequestedJournal(): void
    {
        $this->assertCriteria(['rvid' => 8], ['vid' => 1083, 'rvid' => 8]);
    }

    public function testVolumeIsSearchedInAllJournalsWithoutJournal(): void
    {
        $this->assertCriteria([], ['vid' => 1083]);
    }

    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['abc'])]
    public function testPageLowerThanOneIsRejected(string $page): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createStub(VolumeRepository::class));

        $pagination = new Pagination();

        $provider = new VolumeStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            $pagination,
            $this->createStub(Security::class),
            new UnpaginatedCollectionLimitExtension($pagination, $this->createStub(ManagerRegistry::class), new RequestStack())
        );

        $operation = new GetCollection(class: Volume::class);

        $this->expectException(BadRequestHttpException::class);

        $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['page' => $page]]);
    }

    private function assertCriteria(array $filters, array $expectedCriteria): void
    {
        $volume = new Volume();

        $volumeRepository = $this->createMock(VolumeRepository::class);
        $volumeRepository->expects($this->once())->method('findOneByWithContext')->with($expectedCriteria)->willReturn($volume);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($volumeRepository);

        $pagination = new Pagination();

        $provider = new VolumeStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            $pagination,
            $this->createStub(Security::class),
            new UnpaginatedCollectionLimitExtension($pagination, $this->createStub(ManagerRegistry::class), new RequestStack())
        );

        $operation = new Get(class: Volume::class);

        $this->assertSame($volume, $provider->provide($operation, ['vid' => 1083], ['operation' => $operation, 'filters' => $filters]));
    }

    /**
     * @param Volume[] $volumes volumes returned by the database
     * @return mixed
     */
    private function provide(array $volumes): mixed
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($volumes);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getDQLPart')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(self::MAX_ITEMS + 1)->willReturnSelf();

        $volumeRepository = $this->createStub(VolumeRepository::class);
        $volumeRepository->method('listQuery')->willReturn($queryBuilder);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($volumeRepository);

        $pagination = new Pagination(['client_enabled' => true, 'maximum_items_per_page' => self::MAX_ITEMS]);

        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        $provider = new VolumeStateProvider(
            $entityManager,
            $this->createStub(LoggerInterface::class),
            $pagination,
            $this->createStub(Security::class),
            new UnpaginatedCollectionLimitExtension($pagination, $this->createStub(ManagerRegistry::class), $requestStack)
        );

        $operation = new GetCollection(class: Volume::class);

        return $provider->provide($operation, [], ['operation' => $operation, 'filters' => ['pagination' => 'false']]);
    }

    /**
     * @return Volume[]
     */
    private function createVolumes(int $count): array
    {
        return array_map(static fn(): Volume => new Volume(), range(1, $count));
    }
}
