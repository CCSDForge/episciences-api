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
use ApiPlatform\OpenApi\Model\Schema;
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

        $this->assertSame('Invalid pagination parameter (e.g. `page` lower than 1)', $get->getResponses()[400]->getDescription());
        $this->assertSame('#/components/schemas/Error.jsonld', $get->getResponses()[400]->getContent()['application/ld+json']->getSchema()['$ref']);
        $this->assertSame('#/components/schemas/Error', $get->getResponses()[400]->getContent()['application/json']->getSchema()['$ref']);
    }

    public function testCollectionServedByAControllerIsUnchanged(): void
    {
        $get = $this->createOpenApi()->getPaths()->getPath(self::CONTROLLER_PATH)->getGet();

        $this->assertSame('Enable or disable pagination', $get->getParameters()[0]->getDescription());
        $this->assertNull($get->getResponses()[200]->getHeaders());
        $this->assertArrayNotHasKey(400, $get->getResponses());
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

        // 401: /api/volumes accepts an optional JWT
        $this->assertSame([200, 404, 401], array_keys($byJournal));
        $this->assertSame([200], array_keys($withoutJournal));
    }

    public function testNotFoundResponseOfTheStatisticsListsTheCheckedFilters(): void
    {
        $paths = $this->createAuthOpenApi()->getPaths();

        $this->assertSame('Journal not found, or invalid `year` or `startAfterDate`', $paths->getPath('/api/statistics/')?->getGet()?->getResponses()[404]->getDescription());
        // the error body of the response is kept
        $this->assertSame('#/components/schemas/Error', $paths->getPath('/api/statistics/')?->getGet()?->getResponses()[404]->getContent()['application/problem+json']->getSchema()['$ref']);
        $this->assertSame('Journal not found, or invalid `year`', $paths->getPath('/api/statistics/evaluation')?->getGet()?->getResponses()[404]->getDescription());
        // without year nor startAfterDate
        $this->assertSame('Not found', $paths->getPath('/api/journals/stats/nb-users/{code}')?->getGet()?->getResponses()[404]->getDescription());
        $this->assertSame('Journal not found', $paths->getPath('/api/volumes')?->getGet()?->getResponses()[404]->getDescription());
    }

    public function testErrorResponsesWithoutBodyGetTheErrorBody(): void
    {
        $responses = $this->createAuthOpenApi()->getPaths()->getPath('/api/me')?->getGet()?->getResponses();

        // 403 declared without body
        $this->assertSame('#/components/schemas/Error.jsonld', $responses[403]->getContent()['application/ld+json']->getSchema()['$ref']);
        // 401: body of the JWT authentication, not documented
        $this->assertNull($responses[401]->getContent());
    }

    public function testOptionalJwtIsDocumentedOnDoubleModeOperations(): void
    {
        $operation = $this->createAuthOpenApi()->getPaths()->getPath('/api/volumes')?->getGet();

        // Anonymous ({}) or bearer
        $this->assertSame('[{},{"bearerAuth":[]}]', json_encode($operation?->getSecurity()));
        $this->assertArrayHasKey(401, $operation?->getResponses() ?? []);
        $this->assertStringContainsString('JWT', (string)$operation?->getDescription());
    }

    public function testPublicOperationWithoutJwtIsUnchanged(): void
    {
        $operation = $this->createAuthOpenApi()->getPaths()->getPath('/api/news')?->getGet();

        $this->assertNull($operation?->getSecurity());
        $this->assertSame([200], array_keys($operation?->getResponses() ?? []));
    }

    public function testBearerSecuritySchemeIsDeclared(): void
    {
        $schemes = $this->createAuthOpenApi()->getComponents()->getSecuritySchemes();

        $this->assertArrayHasKey(OpenApiFactory::BEARER_AUTH, $schemes);
        $this->assertSame('bearer', $schemes[OpenApiFactory::BEARER_AUTH]['scheme']);
    }

    public function testConfidentialityNoteIsAddedToTheJsonSchemas(): void
    {
        $schemas = $this->createSchemasOpenApi([
            'Paper-read.Paper' => [
                'type' => 'object',
                'properties' => new \ArrayObject([
                    'editors' => new \ArrayObject(['type' => 'array', 'description' => 'Assigned editors. ']),
                    'docid' => new \ArrayObject(['type' => 'integer']),
                ]),
            ],
        ])->getComponents()->getSchemas();

        $properties = $schemas['Paper-read.Paper']['properties'];
        $this->assertSame('Assigned editors. ' . OpenApiFactory::NOTE_PAPERS_MANAGE, $properties['editors']['description']);
        $this->assertArrayNotHasKey('description', $properties['docid']);
    }

    public function testConfidentialityNoteIsAddedToTheJsonLdSchemasAndKeepsTheEmptyObjects(): void
    {
        $schemas = $this->createSchemasOpenApi([
            'Paper.jsonld-read.Paper' => [
                'allOf' => [
                    new \ArrayObject(['$ref' => '#/components/schemas/HydraItemBaseSchema']),
                    new \ArrayObject([
                        'type' => 'object',
                        'properties' => new \ArrayObject([
                            'editors' => new \ArrayObject(['type' => 'array', 'items' => new \ArrayObject()]),
                        ]),
                    ]),
                ],
            ],
        ])->getComponents()->getSchemas();

        $editors = $schemas['Paper.jsonld-read.Paper']['allOf'][1]['properties']['editors'];
        $this->assertSame(OpenApiFactory::NOTE_PAPERS_MANAGE, $editors['description']);
        $this->assertSame('{}', json_encode($editors['items']));
    }

    public function testConfidentialityNoteDoesNotAlterSharedDefinitions(): void
    {
        $shared = new \ArrayObject(['type' => 'string']);

        $schemas = $this->createSchemasOpenApi([
            'User-read.User' => ['properties' => new \ArrayObject(['username' => $shared])],
            'User.jsonld-read.User' => ['properties' => new \ArrayObject(['username' => $shared])],
            'PaperLog-read.PaperLog' => ['properties' => new \ArrayObject(['user' => $shared])],
        ])->getComponents()->getSchemas();

        $this->assertSame(OpenApiFactory::NOTE_ROLE_MEMBER, $schemas['User-read.User']['properties']['username']['description']);
        $this->assertSame(OpenApiFactory::NOTE_ROLE_MEMBER, $schemas['User.jsonld-read.User']['properties']['username']['description']);
        $this->assertArrayNotHasKey('description', $schemas['PaperLog-read.PaperLog']['properties']['user']);
        $this->assertArrayNotHasKey('description', $shared);
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
        $paths->addPath('/api/statistics/', new PathItem(get: new Operation(
            responses: [200 => new Response('OK')],
            parameters: [new Parameter('rvcode', 'query'), new Parameter('year', 'query'), new Parameter('year[]', 'query'), new Parameter('startAfterDate', 'query')]
        )));
        $paths->addPath('/api/statistics/evaluation', new PathItem(get: new Operation(
            responses: [200 => new Response('OK')],
            parameters: [new Parameter('rvcode', 'query'), new Parameter('year[]', 'query')]
        )));
        $paths->addPath('/api/journals/stats/nb-users/{code}', new PathItem(get: new Operation(
            responses: [200 => new Response('OK'), 404 => new Response('Not found')],
            parameters: [new Parameter('code', 'path')]
        )));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject()))
        );

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([]));

        return new OpenApiFactory($decorated, $resourceNameCollectionFactory, $this->createStub(ResourceMetadataCollectionFactoryInterface::class))();
    }

    /**
     * Spec with the given component schemas only (field confidentiality tests).
     *
     * @param array<string, array<string, mixed>> $definitions Schema name -> schema definition
     */
    private function createSchemasOpenApi(array $definitions): OpenApi
    {
        $schemas = [];
        foreach ($definitions as $name => $definition) {
            $schema = new Schema();
            $schema->exchangeArray($definition);
            $schemas[$name] = $schema;
        }

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], new Paths(), new Components(schemas: new \ArrayObject($schemas), securitySchemes: new \ArrayObject()))
        );

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([]));

        return new OpenApiFactory($decorated, $resourceNameCollectionFactory, $this->createStub(ResourceMetadataCollectionFactoryInterface::class))();
    }
}
