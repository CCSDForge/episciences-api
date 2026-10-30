<?php


namespace App\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities as EventPrioritiesAlias;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Exception\InvalidQueryParameter;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use App\Traits\CheckExistingResourceTrait;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StatResourceSubscriber implements EventSubscriberInterface
{

    use CheckExistingResourceTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['checkStatResourceAvailability', EventPrioritiesAlias::PRE_VALIDATE],
        ];
    }

    /**
     * @param ViewEvent $event
     * @throws ValidationException an unknown parameter
     * @throws ResourceNotFoundException
     * @throws Exception
     */
    public function checkStatResourceAvailability(ViewEvent $event): void
    {

        if (!$event->getRequest()->isMethodSafe()){
            return;
        }

        $statResource  = $event->getControllerResult();

        if (!$statResource instanceof AbstractStatResource) {
            return;
        }
        

        $arrayDiff = $this->checkFilters($statResource->getAvailableFilters(), $statResource->getRequestedFilters());

        if($arrayDiff['equality']){
            return;
        }

        if (!empty($arrayDiff['arrayDiff']['out'])) {
            // an unknown parameter is a client error, as an invalid value (422)
            throw InvalidQueryParameter::exception(
                array_values($arrayDiff['arrayDiff']['out']),
                sprintf('Not available. The available parameters are: %s.', json_encode($statResource->getAvailableFilters(), JSON_THROW_ON_ERROR))
            );
        }

        if ($statResource->getValue() === null) {
            throw new ResourceNotFoundException(sprintf('%s does not exist.', $statResource));
        }
    }
}