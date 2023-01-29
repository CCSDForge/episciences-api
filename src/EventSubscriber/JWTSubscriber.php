<?php

namespace App\EventSubscriber;

use App\Entity\Main\User;
use App\Entity\Main\UserRoles;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JWTSubscriber implements EventSubscriberInterface
{
    public function onLexikJwtAuthenticationOnJwtCreated(JWTCreatedEvent $event): void
    {
        $data = $event->getData();
        $roles = [];

        /** @var User $user */
        $user = $event->getUser();

//        /* @var UserRoles $userRole */
//        foreach ($user->getUserRoles()->toArray() as $userRole) {
//            $roles[$userRole->getRvid()][] = 'ROLE_' . strtoupper($userRole->getRoleid());
//        }
//
//        $data['roles'] = $roles;


        $data['username'] = $user->getUsername();
        $event->setData($data);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created' => 'onLexikJwtAuthenticationOnJwtCreated',
        ];
    }
}
