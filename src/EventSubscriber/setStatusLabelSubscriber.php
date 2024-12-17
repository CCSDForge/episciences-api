<?php

namespace App\EventSubscriber;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Paper;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class setStatusLabelSubscriber implements EventSubscriberInterface
{

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function setStatusLabel(ViewEvent $event): void
    {
        $object = $event->getControllerResult();

        if ($object instanceof Paper || $object instanceof Paginator) {

            if ($object instanceof Paper) {
                $object->setStatusLabel(Paper::STATUS_DICTIONARY[$object->getStatus()]);

            } else {

                try {
                    foreach ($object->getIterator() as $value) {

                        if (!($value instanceof Paper)) {
                            continue;
                        }

                        $value->setStatusLabel(Paper::STATUS_DICTIONARY[$value->getStatus()]);
                    }
                } catch (Exception $e) {
                    $this->logger->critical($e->getMessage());

                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['setStatusLabel', EventPriorities::PRE_SERIALIZE],
        ];
    }

}
