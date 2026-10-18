<?php

namespace App\EventSubscriber;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\State\Pagination\Pagination;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An invalid pagination parameter sent by the client (page lower than 1, negative itemsPerPage...)
 * is reported as a 400 error instead of a server error.
 * Only the exceptions thrown by the API Platform Pagination service are converted.
 */
final class InvalidPaginationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (
            $exception instanceof InvalidArgumentException &&
            $exception->getFile() === new \ReflectionClass(Pagination::class)->getFileName()
        ) {
            $event->setThrowable(new BadRequestHttpException($exception->getMessage(), $exception));
        }
    }
}
