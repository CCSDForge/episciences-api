<?php

namespace App\Listeners;


use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Main\Papers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

class UserListener implements EventSubscriberInterface {

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                'addCurrentUser', EventPriorities::PRE_VALIDATE
            ]
        ];
    }

    public function addCurrentUser(ViewEvent $event): void
    {
        $paper = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        //$route = $event->getRequest()->getRequestUri();

        if ($paper instanceof Papers && $method === "POST"){
            $paper->setAuthor($this->security->getUser());
        }
    }
}