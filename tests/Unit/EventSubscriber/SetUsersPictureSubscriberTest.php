<?php

namespace App\Tests\Unit\EventSubscriber;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Entity\User;
use App\EventSubscriber\SetUsersPictureSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SetUsersPictureSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ParameterBagInterface&MockObject $parameters;
    private SetUsersPictureSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger     = $this->createMock(LoggerInterface::class);
        $this->parameters = $this->createMock(ParameterBagInterface::class);
        $this->parameters->method('get')
            ->with('app.user.picture.path')
            ->willReturn('/var/pictures');

        $this->subscriber = new SetUsersPictureSubscriber($this->logger, $this->parameters);
    }

    // ------------------------------------------------------------------ helpers

    private function makeEvent(mixed $result): ViewEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/users');
        return new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $result);
    }

    // ------------------------------------------------------------------ metadata

    public function testGetSubscribedEventsReturnsPreSerializeOnViewEvent(): void
    {
        $events = SetUsersPictureSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('setUsersPicture', $events[KernelEvents::VIEW][0]);
    }

    // ------------------------------------------------------------------ User item

    public function testSingleUserHasPicturePathProcessed(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('processPicturePath')->with('/var/pictures');

        $this->subscriber->setUsersPicture($this->makeEvent($user));
    }

    // ------------------------------------------------------------------ ArrayPaginator collection

    public function testPaginatedCollectionProcessesEachUser(): void
    {
        /** @var User&MockObject $user1 */
        $user1 = $this->createMock(User::class);
        $user1->expects($this->once())->method('processPicturePath')->with('/var/pictures');

        /** @var User&MockObject $user2 */
        $user2 = $this->createMock(User::class);
        $user2->expects($this->once())->method('processPicturePath')->with('/var/pictures');

        $paginator = new ArrayPaginator([$user1, $user2], 0, 2);
        $this->subscriber->setUsersPicture($this->makeEvent($paginator));
    }

    // ------------------------------------------------------------------ non-User item inside paginator is ignored

    public function testNonUserItemInsidePaginatorIsIgnored(): void
    {
        $paginator = new ArrayPaginator([new \stdClass()], 0, 1);
        $this->subscriber->setUsersPicture($this->makeEvent($paginator));
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ unrelated result is ignored

    public function testUnrelatedResultIsIgnored(): void
    {
        $this->subscriber->setUsersPicture($this->makeEvent(new \stdClass()));
        $this->addToAssertionCount(1);
    }

    public function testNullResultIsIgnored(): void
    {
        $this->subscriber->setUsersPicture($this->makeEvent(null));
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ exception inside paginator is logged as critical

    public function testExceptionInsidePaginatorIsLoggedAsCritical(): void
    {
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $user->method('processPicturePath')->willThrowException(new \RuntimeException('disk error'));

        $paginator = new ArrayPaginator([$user], 0, 1);

        $this->logger->expects($this->once())->method('critical')->with('disk error');

        $this->subscriber->setUsersPicture($this->makeEvent($paginator));
    }
}
