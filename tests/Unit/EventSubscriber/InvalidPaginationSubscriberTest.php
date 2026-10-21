<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\State\Pagination\Pagination;
use App\EventSubscriber\InvalidPaginationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An invalid pagination parameter (?page=0) is a client error (400), not a server error.
 */
class InvalidPaginationSubscriberTest extends TestCase
{
    public function testPaginationErrorBecomesBadRequest(): void
    {
        try {
            new Pagination()->getPage(['filters' => ['page' => '0']]);
            $this->fail('Pagination::getPage() accepted page 0');
        } catch (InvalidArgumentException $exception) {
        }

        $event = $this->dispatch($exception);

        $this->assertInstanceOf(BadRequestHttpException::class, $event->getThrowable());
        $this->assertSame('Page should not be less than 1', $event->getThrowable()->getMessage());
    }

    public function testOtherInvalidArgumentExceptionIsKept(): void
    {
        $exception = new InvalidArgumentException('Unrelated error');

        $this->assertSame($exception, $this->dispatch($exception)->getThrowable());
    }

    /**
     * The error must be converted before Symfony logs it, otherwise it is logged as a server error.
     */
    public function testRunsBeforeTheErrorLogging(): void
    {
        $dispatcher = new EventDispatcher();
        // Registered last: only the priority can make it run first
        $dispatcher->addSubscriber(new ErrorListener(null));
        $dispatcher->addSubscriber(new InvalidPaginationSubscriber());

        $listeners = array_map(
            static fn(callable $listener): string => $listener[1],
            $dispatcher->getListeners(KernelEvents::EXCEPTION)
        );

        $this->assertLessThan(array_search('logKernelException', $listeners, true), array_search('onKernelException', $listeners, true));
    }

    private function dispatch(\Throwable $exception): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);

        new InvalidPaginationSubscriber()->onKernelException($event);

        return $event;
    }
}
