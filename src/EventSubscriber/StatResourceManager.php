<?php


namespace App\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Exception\StatResourceNotFoundException;
use App\Resource\StatResource;
use App\Traits\CheckExistingResourceTrait;
use Generator;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StatResourceManager implements EventSubscriberInterface
{

    use CheckExistingResourceTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['checkStatResourceAvailability', EventPriorities::PRE_VALIDATE],
        ];
    }

    /**
     * @param ViewEvent $event
     * @throws StatResourceNotFoundException
     * @throws Exception
     */
    public function checkStatResourceAvailability(ViewEvent $event): void
    {
        /** @var  Generator $generator */
        $generator = $event->getControllerResult();

        if (!$generator instanceof Generator) {
            return;
        }

        $statResource = $generator->current();

        if (!$statResource instanceof StatResource || !$event->getRequest()->isMethodSafe()) {
            return;
        }

        $arrayDiff = $this->checkFilters($statResource->getAvailableFilters(), $statResource->getRequestedFilters());

        if($arrayDiff['equality']){
            return;
        }

        if (!empty($arrayDiff['arrayDiff']['out'])) {
            throw new StatResourceNotFoundException(sprintf('%s does not exist: Parameter(s) %s not available. The available parameters are: %s ', $statResource, json_encode(array_values($arrayDiff['arrayDiff']['out']), JSON_THROW_ON_ERROR), json_encode($statResource->getAvailableFilters(), JSON_THROW_ON_ERROR)));
        }

        if ($statResource->getValue() === null) {
            throw new StatResourceNotFoundException(sprintf('%s does not exist.', $statResource));
        }
    }
}