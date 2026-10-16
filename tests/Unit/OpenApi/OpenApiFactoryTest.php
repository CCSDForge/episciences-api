<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\OpenApiFactory;
use PHPUnit\Framework\TestCase;

class OpenApiFactoryTest extends TestCase
{
    private OpenApi $openApi;

    protected function setUp(): void
    {
        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $paths = new Paths();
        $paths->addPath('/api/me', new PathItem(get: new Operation(
            responses: [200 => new Response('OK'), 403 => new Response('Forbidden')],
            security: [[OpenApiFactory::BEARER_AUTH => []]]
        )));
        $paths->addPath('/api/news', new PathItem(get: new Operation(responses: [200 => new Response('OK')])));

        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject()))
        );

        $this->openApi = (new OpenApiFactory($decorated))();
    }

    public function testAuthOperationsAreDocumented(): void
    {
        foreach ([OpenApiFactory::LOGIN_PATH, OpenApiFactory::REFRESH_PATH] as $path) {
            $operation = $this->openApi->getPaths()->getPath($path)?->getPost();

            $this->assertNotNull($operation, $path);
            $this->assertNotEmpty($operation->getSummary(), $path);
            $this->assertArrayHasKey(200, $operation->getResponses(), $path);
            $this->assertArrayHasKey(401, $operation->getResponses(), $path);
            $this->assertTrue($operation->getRequestBody()?->getRequired(), $path);
        }
    }

    public function testRefreshTokenDoesNotRequireBearerAuth(): void
    {
        $operation = $this->openApi->getPaths()->getPath(OpenApiFactory::REFRESH_PATH)?->getPost();

        $this->assertNull($operation?->getSecurity());
    }

    public function testUnauthorizedResponseIsAddedToBearerSecuredOperations(): void
    {
        $secured = $this->openApi->getPaths()->getPath('/api/me')?->getGet()?->getResponses();
        $public = $this->openApi->getPaths()->getPath('/api/news')?->getGet()?->getResponses();

        $this->assertSame([200, 403, 401], array_keys($secured));
        $this->assertSame([200], array_keys($public));
    }

    public function testBearerSecuritySchemeIsDeclared(): void
    {
        $schemes = $this->openApi->getComponents()->getSecuritySchemes();

        $this->assertArrayHasKey(OpenApiFactory::BEARER_AUTH, $schemes);
        $this->assertSame('bearer', $schemes[OpenApiFactory::BEARER_AUTH]['scheme']);
    }
}
