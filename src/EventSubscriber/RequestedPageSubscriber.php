<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Exception\ResourceNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Only an existing page of a paginated collection may be requested, whatever serves it
 * (Doctrine, a state provider or a controller):
 * - a page that is not a positive integer (`0`, `-1`, `1.5`, `2abc`, `abc`...) is a bad request (400),
 *   rejected before the collection is read;
 * - a page after the last one is not found (404): a page after the first one that holds no item.
 *   The first page always exists, even empty.
 *
 * The page is ignored when the pagination is disabled (`pagination=false` or on the operation).
 * A page sent as a list is left to SingleValueQueryParametersResourceMetadataCollectionFactory (422).
 */
final readonly class RequestedPageSubscriber implements EventSubscriberInterface
{
    public function __construct(private Pagination $pagination)
    {
    }

    /**
     * Offset of a page of the collections paginated by a controller (ArrayPaginator), the page being already validated
     * (positive integer). A page whose offset is not an integer is a bad request (400), as for the Doctrine collections
     * (Pagination::getOffset()), instead of a TypeError (500).
     *
     * @throws BadRequestHttpException
     */
    public static function firstResult(int $page, int $itemsPerPage): int
    {
        if ($page - 1 > intdiv(PHP_INT_MAX, max(1, $itemsPerPage))) {
            throw new BadRequestHttpException('Page parameter is too large.');
        }

        return ($page - 1) * $itemsPerPage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the firewall (8) and the operation resolution (AddFormatListener, 28),
            // before the collection is read (ReadListener, 4)
            KernelEvents::REQUEST => ['checkPageFormat', 5],
            // Before the result is written and serialized (WriteListener, 32)
            KernelEvents::VIEW => ['checkPageExists', 40],
        ];
    }

    public function checkPageFormat(RequestEvent $event): void
    {
        $page = $this->requestedPage($event->getRequest());

        if (is_string($page) && !preg_match('/^[1-9]\d*$/', $page)) {
            throw new BadRequestHttpException('Page should be a positive integer');
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function checkPageExists(ViewEvent $event): void
    {
        $page = $this->requestedPage($event->getRequest());
        $result = $event->getControllerResult();

        if (!is_string($page) || (int)$page <= 1 || !(is_array($result) || $result instanceof \Countable)) {
            return;
        }

        if (count($result) === 0) {
            throw new ResourceNotFoundException(sprintf('Oops! page %s is out of range: it is not available', $page));
        }
    }

    /**
     * Page requested on a paginated collection, as sent by the client (null when none applies).
     */
    private function requestedPage(Request $request): mixed
    {
        $operation = $request->attributes->get('_api_operation');

        if (!$operation instanceof Operation || !$operation instanceof CollectionOperationInterface) {
            return null;
        }

        $pageParameter = $this->pagination->getOptions()['page_parameter_name'] ?? 'page';

        if (!$request->query->has($pageParameter)) {
            return null;
        }

        if (!$this->pagination->isEnabled($operation, ['filters' => $request->query->all()])) {
            return null;
        }

        return $request->query->all()[$pageParameter];
    }
}
