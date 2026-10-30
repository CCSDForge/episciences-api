<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\OpenApi;
use App\AppConstants;
use App\Controller\BoardsController;
use App\Entity\Paper;
use App\Entity\Review;
use App\EventSubscriber\TruncatedCollectionSubscriber;
use App\Metadata\SingleValueQueryParametersResourceMetadataCollectionFactory;
use App\OpenApi\OpenApiFactory;
use App\OpenApi\QueryParameters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

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

    public function testSingleAndListFormsOfAFilterAreDocumentedOnce(): void
    {
        $parameters = $this->documentParameters([
            new Parameter('page', 'query'),
            new Parameter('vid', 'query', 'Volume identifier', schema: ['type' => 'integer']),
            new Parameter('vid[]', 'query', schema: ['type' => 'array', 'items' => ['type' => 'integer']], explode: true),
        ]);

        $this->assertSame(['page', 'vid[]'], array_map(static fn(Parameter $parameter): string => $parameter->getName(), $parameters));
        $this->assertSame('Volume identifier. One or several values: `vid[]=…&vid[]=…`, or `vid=…` for a single one.', $parameters[1]->getDescription());
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $parameters[1]->getSchema());
        $this->assertSame('form', $parameters[1]->getStyle());
        $this->assertTrue($parameters[1]->getExplode());
    }

    public function testListFormOfAFilterTakesTheItemSchemaOfItsSingleForm(): void
    {
        $parameters = $this->documentParameters([
            new Parameter('indicator', 'query', schema: ['type' => 'string', 'enum' => ['a', 'b']]),
            new Parameter('indicator[]', 'query', 'Several indicators', schema: ['type' => 'array']),
        ]);

        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a', 'b']]], $parameters[0]->getSchema());
        // the description of the list form when the single form has none
        $this->assertStringStartsWith('Several indicators. One or several values: ', $parameters[0]->getDescription());
    }

    public function testListFormDeclaredAloneGetsTheSyntaxNote(): void
    {
        $parameters = $this->documentParameters([new Parameter('year[]', 'query', 'Publication year(s).', schema: ['type' => 'array', 'items' => ['type' => 'integer']])]);

        $this->assertSame('Publication year(s). One or several values: `year[]=…&year[]=…`, or `year=…` for a single one.', $parameters[0]->getDescription());
    }

    public function testJournalCodeStaysASingleValue(): void
    {
        $parameters = $this->documentParameters([new Parameter('rvcode', 'query'), new Parameter('rvcode[]', 'query', schema: ['type' => 'array'])]);

        $this->assertCount(1, $parameters);
        $this->assertSame('rvcode', $parameters[0]->getName());
        $this->assertSame(QueryParameters::JOURNAL_CODE_DESCRIPTION, $parameters[0]->getDescription());
    }

    public function testFilterParametersWithoutDescriptionGetOne(): void
    {
        $parameters = $this->documentParameters([
            new Parameter('vid', 'query'),
            new Parameter('vid[]', 'query', schema: ['type' => 'array', 'items' => ['type' => 'integer']]),
            new Parameter('publicationDate[strictly_after]', 'query'),
            new Parameter('unknown', 'query'),
        ]);

        $this->assertStringStartsWith(QueryParameters::FILTER_DESCRIPTIONS['vid'] . '. One or several values: ', $parameters[0]->getDescription());
        $this->assertSame('Only the items whose `publicationDate` is after this date (YYYY-MM-DD)', $parameters[1]->getDescription());
        $this->assertSame('', $parameters[2]->getDescription());
    }

    public function testDeclaredDescriptionIsKept(): void
    {
        $parameters = $this->documentParameters([new Parameter('vid', 'query', 'Volume of the paper')]);

        $this->assertSame('Volume of the paper', $parameters[0]->getDescription());
    }

    public function testOtherParametersAreUnchanged(): void
    {
        $parameters = [
            new Parameter('docid', 'path', 'Paper identifier', true),
            new Parameter('order[title]', 'query'),
            new Parameter('code', 'query', 'Code of the journal'),
        ];

        $this->assertEquals($parameters, $this->documentParameters($parameters));
    }

    public function testUnprocessableEntityDescribesTheValidatedParametersDocumentedByTheOperation(): void
    {
        $responses = $this->createValidationOpenApi()->getPaths()->getPath('/api/papers/')?->getGet()?->getResponses();

        // `letter` is validated but not documented by the operation
        $this->assertSame('Invalid `scope` (see the accepted values), or `rvcode` or `page` given as a list (`name[]=…`): a single value is expected', $responses[422]->getDescription());
        // the response documented by API Platform (ConstraintViolation schema) is kept, after the success response
        $this->assertSame('#/components/schemas/ConstraintViolation', $responses[422]->getContent()['application/problem+json']->getSchema()['$ref']);
        $this->assertGreaterThan(array_search(200, array_keys($responses), true), array_search(422, array_keys($responses), true));
    }

    public function testItemGetsTheUnprocessableEntityOfTheCollections(): void
    {
        $responses = $this->createValidationOpenApi()->getPaths()->getPath('/api/volumes/{vid}')?->getGet()?->getResponses();

        $this->assertSame('`rvcode` given as a list (`name[]=…`): a single value is expected', $responses[422]->getDescription());
        $this->assertSame('#/components/schemas/ConstraintViolation', $responses[422]->getContent()['application/problem+json']->getSchema()['$ref']);
    }

    public function testUnprocessableEntityIsRemovedWithoutDocumentedValidatedParameter(): void
    {
        $responses = $this->createValidationOpenApi()->getPaths()->getPath('/api/feed/rss/{code}')?->getGet()?->getResponses();

        $this->assertArrayNotHasKey(422, $responses);
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
     * Parameters of an operation as documented by the factory (query parameters tests).
     *
     * @param Parameter[] $parameters
     * @return Parameter[]
     */
    private function documentParameters(array $parameters): array
    {
        $paths = new Paths();
        $paths->addPath('/api/resources', new PathItem(get: new Operation(responses: [200 => new Response('OK')], parameters: $parameters)));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject()))
        );

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([]));

        $openApi = new OpenApiFactory($decorated, $resourceNameCollectionFactory, $this->createStub(ResourceMetadataCollectionFactoryInterface::class))();

        return $openApi->getPaths()->getPath('/api/resources')?->getGet()?->getParameters() ?? [];
    }

    /**
     * Operations validating query parameters (QueryParameter with constraints) and their documentation
     */
    private function createValidationOpenApi(): OpenApi
    {
        $unprocessableEntity = new Response('An error occurred', new \ArrayObject([
            'application/problem+json' => new MediaType(new \ArrayObject(['$ref' => '#/components/schemas/ConstraintViolation'])),
        ]));

        $paths = new Paths();
        $paths->addPath('/api/papers/', new PathItem(get: new Operation(
            responses: [422 => $unprocessableEntity, 200 => new Response('OK')],
            parameters: [new Parameter('rvcode', 'query'), new Parameter('scope', 'query'), new Parameter('page', 'query'), new Parameter('year[]', 'query')]
        )));
        $paths->addPath('/api/volumes/{vid}', new PathItem(get: new Operation(
            responses: [200 => new Response('OK')],
            parameters: [new Parameter('vid', 'path'), new Parameter('rvcode', 'query')]
        )));
        $paths->addPath('/api/feed/rss/{code}', new PathItem(get: new Operation(
            responses: [422 => $unprocessableEntity, 200 => new Response('OK')],
            parameters: [new Parameter('code', 'path')]
        )));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(
            new OpenApi(new Info('Episciences API', '1.0'), [], $paths, new Components(schemas: new \ArrayObject(), securitySchemes: new \ArrayObject()))
        );

        $singleValue = static fn(string $key): QueryParameter => new QueryParameter(
            key: $key,
            constraints: [new Type('string')],
            extraProperties: [SingleValueQueryParametersResourceMetadataCollectionFactory::SINGLE_VALUE => true]
        );

        $resourceNameCollectionFactory = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNameCollectionFactory->method('create')->willReturn(new ResourceNameCollection([Paper::class]));

        $resourceMetadataCollectionFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataCollectionFactory->method('create')->willReturn(new ResourceMetadataCollection(Paper::class, [
            new ApiResource(operations: [
                'papers' => new GetCollection(uriTemplate: '/papers/{._format}', parameters: [
                    'rvcode' => $singleValue('rvcode'),
                    'page' => $singleValue('page'),
                    'letter' => $singleValue('letter'),
                    'scope' => new QueryParameter(key: 'scope', constraints: [new Choice(choices: ['all'])]),
                    // not validated
                    'year' => new QueryParameter(key: 'year'),
                ]),
                'volume' => new Get(uriTemplate: '/volumes/{vid}{._format}', parameters: ['rvcode' => $singleValue('rvcode')]),
                'feed' => new GetCollection(uriTemplate: '/feed/rss/{code}', parameters: ['rvcode' => $singleValue('rvcode')]),
            ]),
        ]));

        return new OpenApiFactory($decorated, $resourceNameCollectionFactory, $resourceMetadataCollectionFactory)();
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
