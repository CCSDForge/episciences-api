<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Entity\User;
use App\EventSubscriber\setUsersPictureSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SetUsersPictureSubscriberTest extends TestCase
{
    private const PICTURE_PATH = '/uploads/pictures';

    private LoggerInterface $logger;
    private ParameterBagInterface $params;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->params->method('get')
            ->with('app.user.picture.path')
            ->willReturn(self::PICTURE_PATH);
    }

    private function makeSubscriber(): setUsersPictureSubscriber
    {
        return new setUsersPictureSubscriber($this->logger, $this->params);
    }

    private function makeEvent(mixed $controllerResult): ViewEvent
    {
        $request = Request::create('/api/users', \Symfony\Component\HttpFoundation\Request::METHOD_GET);

        return new ViewEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $controllerResult);
    }

    // ── getSubscribedEvents ───────────────────────────────────────────────────

    public function testGetSubscribedEventsRegistersViewEvent(): void
    {
        $events = setUsersPictureSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::VIEW, $events);
        $this->assertSame('setUsersPicture', $events[KernelEvents::VIEW][0]);
    }

    // ── non-User/Paginator result is ignored ──────────────────────────────────

    public function testNonUserResultIsIgnored(): void
    {
        $this->params->expects($this->never())->method('get');

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent(new \stdClass());

        $subscriber->setUsersPicture($event);
    }

    public function testNullResultIsIgnored(): void
    {
        $this->params->expects($this->never())->method('get');

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent(null);

        $subscriber->setUsersPicture($event);
    }

    public function testStringResultIsIgnored(): void
    {
        $this->params->expects($this->never())->method('get');

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent('some string');

        $subscriber->setUsersPicture($event);
    }

    // ── single User → processPicturePath called ───────────────────────────────

    public function testSingleUserGetsProcessPicturePath(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('processPicturePath')
            ->with(self::PICTURE_PATH);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($user);

        $subscriber->setUsersPicture($event);
    }

    // ── ArrayPaginator with User items ────────────────────────────────────────

    public function testArrayPaginatorCallsProcessPicturePathForEachUser(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->expects($this->once())->method('processPicturePath')->with(self::PICTURE_PATH);

        $user2 = $this->createMock(User::class);
        $user2->expects($this->once())->method('processPicturePath')->with(self::PICTURE_PATH);

        $paginator = new ArrayPaginator([$user1, $user2], 0, 2);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($paginator);

        $subscriber->setUsersPicture($event);
    }

    // ── ArrayPaginator with mixed items (non-User skipped) ────────────────────

    public function testArrayPaginatorSkipsNonUserItems(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('processPicturePath')->with(self::PICTURE_PATH);

        $paginator = new ArrayPaginator([$user, new \stdClass(), 'some-string'], 0, 3);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($paginator);

        // No exception; only the User mock gets processPicturePath
        $subscriber->setUsersPicture($event);
    }

    // ── ArrayPaginator exception is logged ────────────────────────────────────

    public function testIterationExceptionIsLoggedAsCritical(): void
    {
        $this->logger->expects($this->once())->method('critical');

        $user = $this->createMock(User::class);
        $user->method('processPicturePath')->willThrowException(new \Exception('disk error'));

        $paginator = new ArrayPaginator([$user], 0, 1);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($paginator);

        // Must not throw — exception must be caught and logged
        $subscriber->setUsersPicture($event);
    }

    // ── empty ArrayPaginator ───────────────────────────────────────────────────

    public function testEmptyArrayPaginatorDoesNothing(): void
    {
        $this->params->expects($this->never())->method('get');

        $paginator = new ArrayPaginator([], 0, 0);

        $subscriber = $this->makeSubscriber();
        $event = $this->makeEvent($paginator);

        $subscriber->setUsersPicture($event);
    }
}
