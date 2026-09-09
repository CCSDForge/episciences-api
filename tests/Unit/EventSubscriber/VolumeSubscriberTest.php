<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Paper;
use App\Entity\Volume;
use App\EventSubscriber\VolumeSubscriber;
use App\Repository\VolumeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class VolumeSubscriberTest extends TestCase
{
    private AuthorizationCheckerInterface&MockObject $authChecker;
    private VolumeRepository&MockObject $volumeRepo;
    private LoggerInterface&MockObject $logger;
    private VolumeSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->volumeRepo  = $this->createMock(VolumeRepository::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
        $this->subscriber  = new VolumeSubscriber($this->authChecker, $this->volumeRepo, $this->logger);
    }

    // ------------------------------------------------------------------ helpers

    private function makeEvent(mixed $result): ViewEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/volumes');
        return new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $result);
    }

    // ------------------------------------------------------------------ metadata

    public function testGetSubscribedEventsListensOnViewPreSerialize(): void
    {
        $events = VolumeSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('processVolumePapersCollection', $events[KernelEvents::VIEW][0]);
    }

    // ------------------------------------------------------------------ single Volume item — secretary sees private + public

    public function testSecretarySeesAllPapers(): void
    {
        $paper1 = $this->createMock(Paper::class);
        $paper2 = $this->createMock(Paper::class);

        $privateCollection = new ArrayCollection([$paper1]);
        $publicCollection  = new ArrayCollection([$paper2]);

        $this->volumeRepo
            ->method('fetchSortedPapers')
            ->with(1)
            ->willReturn(['privateCollection' => $privateCollection, 'publicCollection' => $publicCollection]);

        $this->authChecker->method('isGranted')->willReturn(true);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($this->callback(static fn(ArrayCollection $c): bool => count($c) === 2))
            ->willReturnSelf();
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        $this->subscriber->processVolumePapersCollection($this->makeEvent($volume));
    }

    // ------------------------------------------------------------------ single Volume item — non-secretary sees only public

    public function testNonSecretarySeesOnlyPublicPapers(): void
    {
        $paper2 = $this->createMock(Paper::class);
        $publicCollection = new ArrayCollection([$paper2]);

        $this->volumeRepo
            ->method('fetchSortedPapers')
            ->willReturn(['privateCollection' => new ArrayCollection(), 'publicCollection' => $publicCollection]);

        $this->authChecker->method('isGranted')->willReturn(false);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($publicCollection)
            ->willReturnSelf();
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        $this->subscriber->processVolumePapersCollection($this->makeEvent($volume));
    }

    // ------------------------------------------------------------------ empty fetchSortedPapers result falls back to empty collections

    public function testEmptyFetchSortedPapersUsesEmptyCollections(): void
    {
        $this->volumeRepo->method('fetchSortedPapers')->willReturn([]);
        $this->authChecker->method('isGranted')->willReturn(false);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($this->callback(static fn(ArrayCollection $c) => $c->isEmpty()))
            ->willReturnSelf();
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        $this->subscriber->processVolumePapersCollection($this->makeEvent($volume));
    }

    // ------------------------------------------------------------------ array of volumes

    public function testArrayOfVolumesIsProcessed(): void
    {
        $this->volumeRepo->method('fetchSortedPapers')->willReturn([]);
        $this->authChecker->method('isGranted')->willReturn(false);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->expects($this->once())->method('setPapers')->willReturnSelf();
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        $this->subscriber->processVolumePapersCollection($this->makeEvent([$volume]));
    }

    // ------------------------------------------------------------------ array with non-Volume items skips them

    public function testArrayWithNonVolumeItemsSkipsNonVolumes(): void
    {
        // No volumeRepo call expected since no Volume in array
        $this->volumeRepo->expects($this->never())->method('fetchSortedPapers');

        $this->subscriber->processVolumePapersCollection($this->makeEvent([new \stdClass()]));
    }

    // ------------------------------------------------------------------ unrelated result is ignored

    public function testUnrelatedResultIsIgnored(): void
    {
        $this->volumeRepo->expects($this->never())->method('fetchSortedPapers');

        $this->subscriber->processVolumePapersCollection($this->makeEvent(new \stdClass()));
    }

    // ------------------------------------------------------------------ fetchSortedPapers exception in array branch is silently skipped
    // (array branch has no try/catch — exception propagates; covered by Paginator test instead)

    // ------------------------------------------------------------------ multiple volumes in array — each processed independently

    public function testMultipleVolumesInArrayAreEachProcessed(): void
    {
        $this->volumeRepo->method('fetchSortedPapers')->willReturn([]);
        $this->authChecker->method('isGranted')->willReturn(false);

        $volume1 = $this->createMock(Volume::class);
        $volume1->method('getVid')->willReturn(1);
        $volume1->expects($this->once())->method('setPapers')->willReturnSelf();
        $volume1->method('getPapers')->willReturn(new ArrayCollection());

        $volume2 = $this->createMock(Volume::class);
        $volume2->method('getVid')->willReturn(2);
        $volume2->expects($this->once())->method('setPapers')->willReturnSelf();
        $volume2->method('getPapers')->willReturn(new ArrayCollection());

        $this->subscriber->processVolumePapersCollection($this->makeEvent([$volume1, $volume2]));
    }
}
