<?php

namespace App\EventSubscriber;

use App\Doctrine\UnpaginatedCollectionLimitExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells the client that a collection requested without pagination (?pagination=false)
 * was capped by UnpaginatedCollectionLimitExtension: the response holds the
 * TRUNCATED_HEADER header with the maximum number of items (all formats).
 */
final class TruncatedCollectionSubscriber implements EventSubscriberInterface
{
    public const string TRUNCATED_HEADER = 'X-Truncated-Max-Items';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'addTruncatedHeader',
        ];
    }

    public function addTruncatedHeader(ResponseEvent $event): void
    {
        $maxItems = $event->getRequest()->attributes->get(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE);

        if ($maxItems !== null) {
            $event->getResponse()->headers->set(self::TRUNCATED_HEADER, (string)$maxItems);
        }
    }
}
