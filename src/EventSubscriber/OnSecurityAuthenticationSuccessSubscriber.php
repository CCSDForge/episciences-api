<?php

namespace App\EventSubscriber;

use App\Entity\RefreshToken;
use App\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

class OnSecurityAuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    #[NoReturn] public function onSecurityAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        /** @var PostRefreshTokenAuthenticationToken $postRefreshTokenAuthenticationToken */
        $postRefreshTokenAuthenticationToken = $event->getAuthenticationToken();
        /** @var User $user */
        $user = $postRefreshTokenAuthenticationToken->getUser();

        if ($postRefreshTokenAuthenticationToken::class === PostRefreshTokenAuthenticationToken::class  ){

            /** @var RefreshToken $refreshToken */
            $refreshToken = $postRefreshTokenAuthenticationToken->getRefreshToken();
            $user->setCurrentJournalID($refreshToken->getRvId());

        }

    }

    public static function getSubscribedEvents(): array
    {
        return [
            'security.authentication.success' => 'onSecurityAuthenticationSuccess',
        ];
    }
}
