<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Volume;
use App\EventSubscriber\VolumeSubscriber;
use App\Repository\VolumeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class VolumeSubscriberTest extends TestCase
{
    private AuthorizationCheckerInterface $authChecker;
    private VolumeRepository $volumeRepository;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->volumeRepository = $this->createMock(VolumeRepository::class);
    }

    private function makeSubscriber(): VolumeSubscriber
    {
        return new VolumeSubscriber($this->authChecker, $this->volumeRepository, $this->createStub(\Psr\Log\LoggerInterface::class));
    }

    private function makeEvent(mixed $controllerResult): ViewEvent
    {
        $request = Request::create('/api/volumes', \Symfony\Component\HttpFoundation\Request::METHOD_GET);

        return new ViewEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $controllerResult);
    }

    private function makeVolumeMock(int $vid = 1): Volume
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn($vid);
        $volume->method('setPapers')->willReturnSelf();
        $volume->method('getPapers')->willReturn(new ArrayCollection());
        return $volume;
    }

    // ── getSubscribedEvents ───────────────────────────────────────────────────

    public function testGetSubscribedEventsRegistersViewEvent(): void
    {
        $events = VolumeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('processVolumePapersCollection', $events[KernelEvents::VIEW][0]);
    }

    // ── non-Volume/Paginator/array result is ignored ──────────────────────────

    public function testNonVolumeResultIsIgnored(): void
    {
        $this->volumeRepository->expects($this->never())->method('fetchSortedPapers');

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent(new \stdClass());

        $subscriber->processVolumePapersCollection($event);
    }

    public function testNullResultIsIgnored(): void
    {
        $this->volumeRepository->expects($this->never())->method('fetchSortedPapers');

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent(null);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── single Volume item ────────────────────────────────────────────────────

    public function testVolumeItemCallsFetchSortedPapers(): void
    {
        $privateCollection = new ArrayCollection();
        $publicCollection = new ArrayCollection();

        $this->volumeRepository->expects($this->once())
            ->method('fetchSortedPapers')
            ->with(1)
            ->willReturn([
                'privateCollection' => $privateCollection,
                'publicCollection'  => $publicCollection,
            ]);

        $volume = $this->makeVolumeMock(1);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($volume);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── secretary gets private + public papers ────────────────────────────────

    public function testSecretaryReceivesBothPrivateAndPublicPapers(): void
    {
        $this->authChecker->method('isGranted')->with('ROLE_SECRETARY')->willReturn(true);

        $privateCollection = new ArrayCollection(['private-paper']);
        $publicCollection = new ArrayCollection(['public-paper']);

        $this->volumeRepository->method('fetchSortedPapers')->willReturn([
            'privateCollection' => $privateCollection,
            'publicCollection'  => $publicCollection,
        ]);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        // Verify setPapers is called with a merged collection
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($this->callback(fn(ArrayCollection $collection) => $collection->count() === 2
                && in_array('private-paper', $collection->toArray(), true)
                && in_array('public-paper', $collection->toArray(), true)))
            ->willReturnSelf();

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($volume);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── non-secretary gets only public papers ─────────────────────────────────

    public function testNonSecretaryReceivesOnlyPublicPapers(): void
    {
        $this->authChecker->method('isGranted')->with('ROLE_SECRETARY')->willReturn(false);

        $privateCollection = new ArrayCollection(['private-paper']);
        $publicCollection = new ArrayCollection(['public-paper']);

        $this->volumeRepository->method('fetchSortedPapers')->willReturn([
            'privateCollection' => $privateCollection,
            'publicCollection'  => $publicCollection,
        ]);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        // Non-secretary: only public collection
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($this->callback(fn(ArrayCollection $collection) => $collection->count() === 1
                && in_array('public-paper', $collection->toArray(), true)
                && !in_array('private-paper', $collection->toArray(), true)))
            ->willReturnSelf();

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($volume);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── array of Volume objects ───────────────────────────────────────────────

    public function testArrayOfVolumesProcessesEach(): void
    {
        $this->volumeRepository->expects($this->exactly(2))
            ->method('fetchSortedPapers')
            ->willReturn(['publicCollection' => new ArrayCollection()]);

        $volume1 = $this->makeVolumeMock(1);
        $volume2 = $this->makeVolumeMock(2);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent([$volume1, $volume2]);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── array with mixed objects skips non-Volume ─────────────────────────────

    public function testArrayWithMixedObjectsSkipsNonVolumes(): void
    {
        $this->volumeRepository->expects($this->once())
            ->method('fetchSortedPapers')
            ->willReturn(['publicCollection' => new ArrayCollection()]);

        $volume = $this->makeVolumeMock(1);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent([$volume, new \stdClass(), 'not-a-volume']);

        $subscriber->processVolumePapersCollection($event);
    }

    // ── missing sortedPapers keys fall back to empty ArrayCollection ──────────

    public function testMissingPrivateCollectionKeyFallsBackToEmpty(): void
    {
        $this->authChecker->method('isGranted')->willReturn(true); // secretary

        $this->volumeRepository->method('fetchSortedPapers')->willReturn([
            // no 'privateCollection' key
            'publicCollection' => new ArrayCollection(['public-only']),
        ]);

        $volume = $this->createMock(Volume::class);
        $volume->method('getVid')->willReturn(1);
        $volume->method('getPapers')->willReturn(new ArrayCollection());

        // For secretary: private fallback is empty, so merged = just public
        $volume->expects($this->once())
            ->method('setPapers')
            ->with($this->callback(function (ArrayCollection $c) {
                $this->assertCount(1, $c);
                $this->assertSame('public-only', $c->first());
                return true;
            }))
            ->willReturnSelf();

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($volume);

        $subscriber->processVolumePapersCollection($event);
    }
}
