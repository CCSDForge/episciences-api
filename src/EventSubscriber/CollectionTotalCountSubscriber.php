<?php

namespace App\EventSubscriber;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives the total number of items of a collection in the TOTAL_COUNT_HEADER response header,
 * whatever the format: the plain JSON collections hold no metadata, and a client could not
 * tell that the items returned are only part of the collection (a page, or a capped
 * collection requested without pagination).
 *
 * The total is, in this order:
 * - the total set by the provider when the collection was capped (TOTAL_ITEMS_ATTRIBUTE);
 * - none when the collection was capped without a known total (TRUNCATED_ATTRIBUTE only);
 * - the total of the paginator returned by the provider or the controller (a partial paginator has none);
 * - the number of items of a collection returned without pagination.
 *
 * The result is read on kernel.view before API Platform serializes it (SerializeListener, priority 16),
 * so that the collections served by a Symfony controller are covered too.
 */
final class CollectionTotalCountSubscriber implements EventSubscriberInterface
{
    public const string TOTAL_COUNT_HEADER = 'X-Total-Count';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['setTotalCount', 64],
            KernelEvents::RESPONSE => 'addTotalCountHeader',
        ];
    }

    public function setTotalCount(ViewEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !$request->isMethod('GET') ||
            $request->attributes->has(UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE) ||
            $request->attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE) // capped, the total is unknown
        ) {
            return;
        }

        $total = $this->getTotal($request, $event->getControllerResult());

        if ($total !== null) {
            $request->attributes->set(UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE, $total);
        }
    }

    public function addTotalCountHeader(ResponseEvent $event): void
    {
        $total = $event->getRequest()->attributes->get(UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE);

        if (is_int($total) && $event->getRequest()->isMethod('GET') && $event->getResponse()->isSuccessful()) {
            $event->getResponse()->headers->set(self::TOTAL_COUNT_HEADER, (string)$total);
        }
    }

    private function getTotal(Request $request, mixed $result): ?int
    {
        if ($result instanceof PaginatorInterface) {
            return (int)$result->getTotalItems();
        }

        if (is_array($result) && $request->attributes->get('_api_operation') instanceof CollectionOperationInterface) {
            return count($result);
        }

        return null;
    }
}
