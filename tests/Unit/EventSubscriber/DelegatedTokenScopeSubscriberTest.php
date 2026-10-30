<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\DelegatedTokenScopeSubscriber;
use App\Security\DelegatedTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DelegatedTokenScopeSubscriberTest extends TestCase
{
    public function testTokenOfALoginIsNotRestricted(): void
    {
        $this->authenticate([], 'POST', 'api_login_check');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('allowedRouteProvider')]
    public function testDelegatedTokenIsValidOnTheStatistics(string $route): void
    {
        $this->authenticate(['scope' => DelegatedTokenIssuer::SCOPE], 'GET', $route);

        $this->addToAssertionCount(1);
    }

    public static function allowedRouteProvider(): iterable
    {
        foreach (DelegatedTokenIssuer::ALLOWED_ROUTES as $route) {
            yield $route => [$route];
        }
    }

    /**
     * The statistics page of Episciences lists the papers of the dashboard and the reviews of the evaluation.
     */
    public function testDelegatedTokenIsValidOnTheEvaluation(): void
    {
        $this->authenticate(['scope' => DelegatedTokenIssuer::SCOPE], 'GET', '_api_/statistics/evaluation_get_collection');

        $this->addToAssertionCount(1);
    }

    #[DataProvider('refusedUseProvider')]
    public function testDelegatedTokenIsRefusedAnywhereElse(array $payload, string $method, string $route): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->authenticate($payload, $method, $route);
    }

    public static function refusedUseProvider(): iterable
    {
        yield 'other route' => [['scope' => DelegatedTokenIssuer::SCOPE], 'GET', '_api_/papers/{docid}_get'];
        yield 'user data' => [['scope' => DelegatedTokenIssuer::SCOPE], 'GET', '_api_/me_get'];
        yield 'not a GET' => [['scope' => DelegatedTokenIssuer::SCOPE], 'POST', 'get_stats_dashboard_item'];
        yield 'unknown scope' => [['scope' => 'other'], 'GET', 'get_stats_dashboard_item'];
    }

    private function authenticate(array $payload, string $method, string $route): void
    {
        $request = Request::create('/api/whatever', $method);
        $request->attributes->set('_route', $route);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        new DelegatedTokenScopeSubscriber($requestStack)->onJwtAuthenticated(new JWTAuthenticatedEvent($payload, $this->createStub(TokenInterface::class)));
    }
}
