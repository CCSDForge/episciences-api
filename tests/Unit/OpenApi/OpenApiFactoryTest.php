<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\AppConstants;
use App\Controller\BoardsController;
use App\Entity\Review;
use App\EventSubscriber\TruncatedCollectionSubscriber;
use App\OpenApi\OpenApiFactory;
use PHPUnit\Framework\TestCase;

class OpenApiFactoryTest extends TestCase
{
    private const string COLLECTION_PATH = '/api/papers/';
    private const string ITEM_PATH = '/api/papers/{docid}';
    private const string CONTROLLER_PATH = '/api/journals/boards/{code}';

    public function testUnpaginatedCapIsDocumentedOnTheCollections(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::COLLECTION_PATH)->getGet();

        $pagination = $get->getParameters()[0];
        $this->assertSame(
            sprintf(
                'Enable or disable pagination. Without pagination, at most %d items are returned: a truncated response holds the %s header.',
                AppConstants::MAXIMUM_ITEMS_PER_PAGE,
                TruncatedCollectionSubscriber::TRUNCATED_HEADER
            ),
            $pagination->getDescription()
        );

        $headers = $get->getResponses()[200]->getHeaders();
        $this->assertArrayHasKey(TruncatedCollectionSubscriber::TRUNCATED_HEADER, $headers);
        $this->assertSame(['type' => 'integer'], $headers[TruncatedCollectionSubscriber::TRUNCATED_HEADER]['schema']);
    }

    public function testCollectionServedByAControllerIsUnchanged(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::CONTROLLER_PATH)->getGet();

        $this->assertSame('Enable or disable pagination', $get->getParameters()[0]->getDescription());
        $this->assertNull($get->getResponses()[200]->getHeaders());
    }

    public function testOperationWithoutPaginationIsUnchanged(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::ITEM_PATH)->getGet();

        $this->assertNull($get->getResponses()[200]->getHeaders());
    }

    public function testAuthOperationsAreDocumented(): void
    {
        foreach ([OpenApiFactory::LOGIN_PATH, OpenApiFactory::REFRESH_PATH] as $path) {
            $operation = $this->createAuthOpenApi()->getPaths()->getPath($path)?->getPost();

            $this->assertNotNull($operation, $path);
            $this->assertNotEmpty($operation->getSummary(), $path);
            $this->assertArrayHasKey(200, $operation->getResponses(), $path);
            $this->assertArrayHasKey(401, $operation->getResponses(), $path);
            $this->assertTrue($operation->getRequestBody()?->getRequired(), $path);
        }
    }

    public function testRefreshTokenDoesNotRequireBearerAuth(): void
    {
        $operation = $this->createAuthOpenApi()->getPaths()->getPath(OpenApiFactory::REFRESH_PATH)?->getPost();

        $this->assertNull($operation?->getSecurity());
    }

    public function testUnauthorizedResponseIsAddedToBearerSecuredOperations(): void
    {
        $secured = $this->createAuthOpenApi()->getPaths()->getPath('/api/me')?->getGet()?->getResponses();
        $public = $this->createAuthOpenApi()->getPaths()->getPath('/api/news')?->getGet()?->getResponses();

        $this->assertSame([200, 403, 401], array_keys($secured));
        $this->assertSame([200], array_keys($public));
    }

    public function testNotFoundResponseIsAddedToOperationsFilteredByJournal(): void
    {
        $byJournal = $this->createAuthOpenApi()->getPaths()->getPath('/api/volumes')?->getGet()?->getResponses();
        $withoutJournal = $this->createAuthOpenApi()->getPaths()->getPath('/api/news')?->getGet()?->getResponses();

        $this->assertSame([200, 404], array_keys($byJournal));
        $this->assertSame([200], array_keys($withoutJournal));
    }

    public function testBearerSecuritySchemeIsDeclared(): void
    {
        $schemes = $this->createAuthOpenApi()->getComponents()->getSecuritySchemes();

        $this->assertArrayHasKey(OpenApiFactory::BEARER_AUTH, $schemes);
        $this->assertSame('bearer', $schemes[OpenApiFactory::BEARER_AUTH]['scheme']);
    }

    private function createOpenApi(): OpenApi
    {
        $paths = new Paths();
        $paths->addPath(self::COLLECTION_PATH, new PathItem(get: new Operation(
            responses: [200 => new Response('Paper collection')],
            parameters: [new Parameter('pagination', 'query', 'Enable or disable pagination')]
        )));
        $paths->addPath(self::CONTROLLER_PATH, new PathItem(get: new Operation(
            responses: [200 => new Response('Journal boards')],
            parameters: [new Parameter('pagination', 'query', 'Enable or disable pagination')]
        )));
        $paths->addPath(self::ITEM_PATH, new PathItem(get: new Operation(
            responses: [200 => new Response('Paper resource')],
            parameters: [new Parameter('docid', 'path', 'Paper identifier', true)]
        )));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(new OpenApi(new Info('Episciences API', '1'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject())));

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([Review::class]));

        $resourceMetadataCollectionFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataCollectionFactory->method('create')->willReturn(new ResourceMetadataCollection(Review::class, [
            new ApiResource(operations: [
                'boards' => new GetCollection(uriTemplate: '/journals/boards/{code}', controller: BoardsController::class),
                'journals' => new GetCollection(uriTemplate: '/journals/'),
            ]),
        ]));

        return new OpenApiFactory($decorated, $resourceNameCollectionFactory, $resourceMetadataCollectionFactory)();
    }

    /**
     * Paths of the authentication and error responses tests (no API Platform resource).
     */
    private function createAuthOpenApi(): OpenApi
    {
        $paths = new Paths();
        $paths->addPath('/api/me', new PathItem(get: new Operation(
            responses: [200 => new Response('OK'), 403 => new Response('Forbidden')],
            security: [[OpenApiFactory::BEARER_AUTH => []]]
        )));
        $paths->addPath('/api/news', new PathItem(get: new Operation(responses: [200 => new Response('OK')])));
        $paths->addPath('/api/volumes', new PathItem(get: new Operation(
            responses: [200 => new Response('OK')],
            parameters: [new Parameter('rvcode', 'query')]
        )));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject()))
        );

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([]));

        return new OpenApiFactory($decorated, $resourceNameCollectionFactory, $this->createStub(ResourceMetadataCollectionFactoryInterface::class))();
    }
}
