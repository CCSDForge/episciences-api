<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\DelegatedTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A delegated token (see DelegatedTokenIssuer) is only valid on the statistics: refused (403) anywhere else.
 */
final readonly class DelegatedTokenScopeSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [Events::JWT_AUTHENTICATED => 'onJwtAuthenticated'];
    }

    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $scope = $event->getPayload()['scope'] ?? null;

        if ($scope === null) { // a token of a login
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (
            $scope !== DelegatedTokenIssuer::SCOPE ||
            !$request instanceof Request ||
            $request->getMethod() !== Request::METHOD_GET ||
            !in_array($request->attributes->get('_route'), DelegatedTokenIssuer::ALLOWED_ROUTES, true)
        ) {
            throw new AccessDeniedHttpException('This token is only valid on the statistics');
        }
    }
}
