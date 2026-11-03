<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\DelegatedTokenController;
use App\Security\DelegatedTokenIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DelegatedTokenControllerTest extends TestCase
{
    public function testNotFoundWhenDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        new DelegatedTokenController()(Request::create('/api/token/delegated', 'POST', content: '{}'), $this->issuer(''));
    }

    /**
     * The same answer whatever the reason, invalid JSON included.
     */
    public function testInvalidRequestIsUnauthorized(): void
    {
        foreach (['not json', '{}', '{"uid": 7}'] as $content) {
            $response = new DelegatedTokenController()(Request::create('/api/token/delegated', 'POST', content: $content), $this->issuer());

            $this->assertSame(401, $response->getStatusCode());
            $this->assertSame('{"message":"Invalid delegation"}', $response->getContent());
        }
    }

    private function issuer(string $secret = '0123456789abcdef0123456789abcdef'): DelegatedTokenIssuer
    {
        return new DelegatedTokenIssuer(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(JWTTokenManagerInterface::class),
            new ArrayAdapter(),
            new NullLogger(),
            $secret
        );
    }
}
