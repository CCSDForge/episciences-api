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
 *
 * The truncation signal is deliberately carried by this single response header rather
 * than per-item metadata (e.g. a hydra: flag): the cap only ever applies to the top-level
 * collection of the response, and nested arrays are returned "as is" by the normalizers,
 * so there is exactly one collection to report. Json-LD clients get a plain Hydra
 * collection (see ApiCollectionNormalizer) plus this header; the OpenAPI spec documents
 * the header on the capped operations. Keep this contract in mind when the cap is ever
 * extended to nested collections.
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
