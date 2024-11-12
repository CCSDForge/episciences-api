<?php

namespace App\EventSubscriber;

use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\User;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class setUsersPictureSubscriber implements EventSubscriberInterface
{

    public function __construct(private LoggerInterface $logger, private ParameterBagInterface $parameters){}

    public function setUsersPicture(ViewEvent $event): void
    {
        $object = $event->getControllerResult();

        if ($object instanceof ArrayPaginator || $object instanceof User) {

            if ($object instanceof ArrayPaginator) {

                try {
                    foreach ($object->getIterator() as $value) {

                        if ($value instanceof User) {
                            $value->processPicturePath($this->parameters->get('app.user.picture.path'));
                        }

                    }
                } catch (Exception $e) {
                    $this->logger->critical($e->getMessage());
                }
            } else {
                $object->processPicturePath($this->parameters->get('app.user.picture.path'));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['setUsersPicture', EventPriorities::PRE_SERIALIZE],];
    }
}
