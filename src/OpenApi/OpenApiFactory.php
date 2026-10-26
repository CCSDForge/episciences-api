<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use ApiPlatform\OpenApi\OpenApi;
use App\AppConstants;
use App\EventSubscriber\TruncatedCollectionSubscriber;
use Symfony\Component\HttpFoundation\Response;

final readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    public const string OAF_HIDDEN = 'hidden';

    public const array OAF_TAGS = [
        'auth' => 'Sign in - Myspace',
        'stats' => 'Statistics',
        'review' => 'Journals | Boards',
        'user' => 'User',
        'sections_volumes' => 'Sections | Volumes',
        'browse_search' => 'Browse | Search',
        'paper' => 'Papers'
    ];

    public const string LOGIN_PATH = '/api/login';
    public const string REFRESH_PATH = '/api/token/refresh';
    public const string BEARER_AUTH = 'bearerAuth';

    private const array HTTP_METHODS = ['Get', 'Post', 'Put', 'Patch', 'Delete'];

    /** Parameters holding a journal code: an unknown journal returns a 404 */
    private const array JOURNAL_CODE_PARAMETERS = ['code', 'rvcode'];

    /** Statistics paths: an invalid year or start date also returns a 404 */
    private const array STATISTICS_PATH_PREFIXES = ['/api/statistics', '/api/journals/stats/'];

    /** Filters of the statistics rejected with a 404 when invalid */
    private const array STATISTICS_CHECKED_FILTERS = [AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE];

    public const string NOTE_PAPERS_MANAGE = 'Confidential field: only returned to the users allowed to manage the paper (`papers_manage` voter).';
    public const string NOTE_ROLE_SECRETARY = 'Confidential field: only returned to the editorial secretaries (ROLE_SECRETARY).';
    public const string NOTE_ROLE_MEMBER = 'Confidential field: only returned to the authenticated members (ROLE_MEMBER).';
    public const string NOTE_ROLE_EPIADMIN = 'Confidential field: only returned to the EPI administrators (ROLE_EPIADMIN).';

    /**
     * Role-conditioned serialized properties, scoped by owning resource (schema name prefix).
     * Mirrors the `#[ApiProperty(security: ...)]` declarations in the entities.
     *
     * @var array<string, array<string, string>> Resource prefix -> (serialized property -> note)
     */
    public const array SENSITIVE_FIELDS = [
        'Paper' => [
            'flag' => self::NOTE_PAPERS_MANAGE,
            'user' => self::NOTE_PAPERS_MANAGE,
            'assignments' => self::NOTE_PAPERS_MANAGE,
            'editors' => self::NOTE_PAPERS_MANAGE,
            'reviewers' => self::NOTE_PAPERS_MANAGE,
            'copyEditors' => self::NOTE_PAPERS_MANAGE,
            'coAuthors' => self::NOTE_PAPERS_MANAGE,
            'conflicts' => self::NOTE_PAPERS_MANAGE,
        ],
        'Volume' => [
            'settings' => self::NOTE_ROLE_SECRETARY,
        ],
        'Section' => [
            'settings' => self::NOTE_ROLE_SECRETARY,
        ],
        'User' => [
            'username' => self::NOTE_ROLE_MEMBER,
            'registrationDate' => self::NOTE_ROLE_EPIADMIN,
            'modificationDate' => self::NOTE_ROLE_EPIADMIN,
            'isValid' => self::NOTE_ROLE_EPIADMIN,
        ],
        'Review' => [
            'status' => self::NOTE_ROLE_EPIADMIN,
            'creation' => self::NOTE_ROLE_EPIADMIN,
            'piwikid' => self::NOTE_ROLE_EPIADMIN,
        ],
    ];

    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi = $this->filterHiddenEndpoints($openApi);

        $openApi = $this->addMissingResponse(
            $openApi,
            Response::HTTP_UNAUTHORIZED,
            'Authentication required, or invalid or expired JWT token',
            $this->requiresBearerAuth(...)
        );

        $openApi = $this->addMissingResponse(
            $openApi,
            Response::HTTP_NOT_FOUND,
            'Journal not found',
            $this->hasJournalCodeParameter(...)
        );

        $openApi = $this->documentStatisticsNotFound($openApi);

        $openApi = $this->addAuthSchemas($openApi);

        $openApi = $this->addAuthPaths($openApi);

        $openApi = $this->documentUnpaginatedCap($openApi);

        $openApi = $this->documentOptionalAuthMode($openApi);

        $openApi = $this->documentFieldConfidentiality($openApi);

        return $openApi;
    }

    // -----------------------------------
    // CAP OF THE UNPAGINATED COLLECTIONS
    // -----------------------------------
    /**
     * Documents, on the collections that can be requested without pagination, the cap
     * applied to ?pagination=false and the TRUNCATED_HEADER response header.
     * The collections served by a Symfony controller are not capped: they are skipped.
     */
    private function documentUnpaginatedCap(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        $controllerUriTemplates = $this->getControllerCollectionUriTemplates();

        foreach ($paths->getPaths() as $key => $path) {
            foreach ($controllerUriTemplates as $uriTemplate) {
                if (str_ends_with((string)$key, $uriTemplate)) {
                    continue 2;
                }
            }

            $get = $path->getGet();
            $parameters = $get?->getParameters() ?? [];
            $paginationIndex = $this->findPaginationParameter($parameters);

            if ($paginationIndex === null) {
                continue;
            }

            $description = rtrim($parameters[$paginationIndex]->getDescription(), '. ');

            $parameters[$paginationIndex] = $parameters[$paginationIndex]->withDescription(ltrim(sprintf(
                '%s. Without pagination, at most %d items are returned: a truncated response holds the %s header.',
                $description,
                AppConstants::MAXIMUM_ITEMS_PER_PAGE,
                TruncatedCollectionSubscriber::TRUNCATED_HEADER
            ), '. '));

            $paths->addPath($key, $path->withGet(
                $get->withParameters($parameters)->withResponses($this->withTruncatedHeader($get->getResponses() ?? []))
            ));
        }

        return $openApi->withPaths($paths);
    }

    /**
     * @return string[] URI templates (without the route prefix and the format) of the collections served by a Symfony controller
     */
    private function getControllerCollectionUriTemplates(): array
    {
        $uriTemplates = [];

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resource) {
                foreach ($resource->getOperations() ?? [] as $operation) {
                    if ($operation instanceof GetCollection && $operation->getController() !== null && $operation->getUriTemplate() !== null) {
                        $uriTemplates[] = str_replace('{._format}', '', $operation->getUriTemplate());
                    }
                }
            }
        }

        return $uriTemplates;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function findPaginationParameter(array $parameters): int|string|null
    {
        foreach ($parameters as $index => $parameter) {
            if ($parameter instanceof Parameter && $parameter->getName() === 'pagination' && $parameter->getIn() === 'query') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $responses
     * @return array<int|string, mixed>
     */
    private function withTruncatedHeader(array $responses): array
    {
        $response = $responses[Response::HTTP_OK] ?? $responses[(string)Response::HTTP_OK] ?? null;

        if (!$response instanceof OpenApiResponse) {
            return $responses;
        }

        $headers = $response->getHeaders() ?? new \ArrayObject();
        $headers[TruncatedCollectionSubscriber::TRUNCATED_HEADER] = new \ArrayObject([
            'description' => 'Present only when the collection, requested without pagination, was truncated: maximum number of items returned.',
            'schema' => ['type' => 'integer'],
        ]);

        $responses[Response::HTTP_OK] = $response->withHeaders($headers);

        return $responses;
    }

    // -----------------------------------
    // OPTIONAL AUTH (DOUBLE MODE) ENDPOINTS
    // -----------------------------------
    /**
     * Documents the "double mode" of the endpoints that are publicly readable but whose
     * payload/scope changes when a JWT is provided: anonymous calls return only published
     * items, authenticated calls return a richer scope depending on the role.
     *
     * The JWT is optional: the operation declares both an anonymous and a `bearerAuth`
     * requirement, and the 401 returned when a provided token is invalid or expired.
     * This method only documents that behaviour.
     */
    private function documentOptionalAuthMode(OpenApi $openApi): OpenApi
    {
        foreach (array_keys($this->getOptionalAuthPaths()) as $pathKey) {
            $path = $openApi->getPaths()->getPath($pathKey);
            $get = $path?->getGet();

            if (!$get instanceof Operation) {
                continue;
            }

            $description = trim((string)$get->getDescription());
            $note = 'Optional JWT: the access is public by default, but providing a token enlarges or restricts the returned scope according to the role (authenticated mode).';

            $get = $get->withDescription($description !== '' ? $description . ' ' . $note : $note);

            // Optional JWT: anonymous ({}) or bearer; Swagger UI then sends the token once authorized
            $get = $get->withSecurity([new \ArrayObject(), [self::BEARER_AUTH => []]]);

            // A provided JWT is checked even on a public operation
            $responses = $get->getResponses() ?? [];
            $responses[Response::HTTP_UNAUTHORIZED] ??= new OpenApiResponse('Invalid or expired JWT token');
            $get = $get->withResponses($responses);

            $openApi->getPaths()->addPath($pathKey, $path->withGet($get));
        }

        return $openApi;
    }

    /**
     * @return array<string, string> The GET endpoints that behave in "double mode"
     *                               (public + optional JWT enriching the scope).
     */
    private function getOptionalAuthPaths(): array
    {
        return [
            '/api/papers/' => 'Papers',
            '/api/papers/{docid}' => 'Paper',
            '/api/volumes' => 'Volumes',
            '/api/volumes/{vid}' => 'Volume',
            '/api/sections' => 'Sections',
            '/api/sections/{sid}' => 'Section',
            '/api/statistics/' => 'Statistics', // withPapers: administrators of the journal
            '/api/journals/stats/dashboard/{code}' => 'Dashboard', // idem
        ];
    }

    // -----------------------------------
    // FIELD CONFIDENTIALITY
    // -----------------------------------
    /**
     * Documents, on the response schemas, the role-conditioned fields that the
     * generated spec declares unconditionally but that are in fact only exposed
     * to specific roles (see the `#[ApiProperty(security: ...)]` declarations in
     * the entities). Without this, the OpenAPI contract is misleading.
     */
    private function documentFieldConfidentiality(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();
        $schemas = $components->getSchemas();

        foreach ($schemas as $name => $schema) {
            // Owning resource: "Paper.jsonld-read.Paper" (JSON-LD, HTML, XML) or "Paper-read.Paper" (JSON).
            $prefix = substr((string)$name, 0, strcspn((string)$name, '.-'));
            $sensitive = self::SENSITIVE_FIELDS[$prefix] ?? null;
            if ($sensitive === null || !self::isSchemaNode($schema)) {
                continue;
            }

            // Work on the original structure: a JSON round trip would turn the empty objects ({}) into arrays ([]).
            $schemas[$name] = $this->annotateSensitiveProperties($schema, $sensitive);
        }

        return $openApi->withComponents($components->withSchemas($schemas));
    }

    /**
     * Recursively annotates sensitive properties declared in a schema (handles
     * both top-level `properties` and collections of `allOf`/`oneOf`/`anyOf`).
     * Returns a copy: the \ArrayObject instances may be shared between schemas.
     *
     * @param  \ArrayObject<array-key, mixed>|array<array-key, mixed> $schema
     * @param  array<string, string> $sensitive Property name -> note
     * @return \ArrayObject<array-key, mixed>|array<array-key, mixed>
     */
    private function annotateSensitiveProperties(\ArrayObject|array $schema, array $sensitive): \ArrayObject|array
    {
        $schema = self::copyNode($schema);

        $properties = $schema['properties'] ?? null;
        if (self::isSchemaNode($properties)) {
            $properties = self::copyNode($properties);

            foreach ($sensitive as $property => $note) {
                $definition = $properties[$property] ?? null;
                if (!self::isSchemaNode($definition)) {
                    continue;
                }

                $definition = self::copyNode($definition);
                $current = isset($definition['description']) && \is_string($definition['description'])
                    ? rtrim($definition['description'], ' ')
                    : '';

                $definition['description'] = $current !== '' ? $current . ' ' . $note : $note;
                $properties[$property] = $definition;
            }

            $schema['properties'] = $properties;
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $combinator) {
            $subSchemas = $schema[$combinator] ?? null;
            if (!self::isSchemaNode($subSchemas)) {
                continue;
            }

            $subSchemas = self::copyNode($subSchemas);
            foreach ($subSchemas as $index => $subSchema) {
                if (self::isSchemaNode($subSchema)) {
                    $subSchemas[$index] = $this->annotateSensitiveProperties($subSchema, $sensitive);
                }
            }

            $schema[$combinator] = $subSchemas;
        }

        return $schema;
    }

    /**
     * @phpstan-assert-if-true \ArrayObject<array-key, mixed>|array<array-key, mixed> $node
     */
    private static function isSchemaNode(mixed $node): bool
    {
        return $node instanceof \ArrayObject || \is_array($node);
    }

    /**
     * @param  \ArrayObject<array-key, mixed>|array<array-key, mixed> $node
     * @return \ArrayObject<array-key, mixed>|array<array-key, mixed>
     */
    private static function copyNode(\ArrayObject|array $node): \ArrayObject|array
    {
        return $node instanceof \ArrayObject ? clone $node : $node;
    }

    // -----------------------------------
    // REMOVE HIDDEN ENDPOINTS
    // -----------------------------------
    private function filterHiddenEndpoints(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $key => $path) {
            $get = $path->getGet();

            if ($get && $get->getSummary() === self::OAF_HIDDEN) {
                $paths->addPath($key, $path->withGet(null));
            }
        }

        return $openApi->withPaths($paths);
    }

    // -----------------------------------
    // RESPONSES MISSING FROM THE OPERATIONS
    // -----------------------------------
    /**
     * @param callable(Operation): bool $isConcerned
     */
    private function addMissingResponse(OpenApi $openApi, int $status, string $description, callable $isConcerned): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $key => $path) {
            foreach (self::HTTP_METHODS as $method) {
                $operation = $path->{'get' . $method}();

                if (!$operation instanceof Operation || !$isConcerned($operation)) {
                    continue;
                }

                $responses = $operation->getResponses() ?? [];

                if (isset($responses[$status])) {
                    continue;
                }

                $responses[$status] = new OpenApiResponse($description);
                $path = $path->{'with' . $method}($operation->withResponses($responses));
            }

            $paths->addPath($key, $path);
        }

        return $openApi->withPaths($paths);
    }

    /**
     * On the statistics, the 404 is also returned for an invalid year or start date
     */
    private function documentStatisticsNotFound(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $key => $path) {
            $isStatistics = array_filter(self::STATISTICS_PATH_PREFIXES, static fn(string $prefix): bool => str_starts_with((string)$key, $prefix)) !== [];
            $operation = $path->getGet();

            if (!$isStatistics || !$operation instanceof Operation || !isset(($operation->getResponses() ?? [])[Response::HTTP_NOT_FOUND])) {
                continue;
            }

            $filters = [];

            foreach ($operation->getParameters() ?? [] as $parameter) {
                $name = rtrim($parameter->getName(), '[]');

                if (in_array($name, self::STATISTICS_CHECKED_FILTERS, true) && !in_array("`$name`", $filters, true)) {
                    $filters[] = "`$name`";
                }
            }

            if ($filters === []) {
                continue;
            }

            $responses = $operation->getResponses();
            $responses[Response::HTTP_NOT_FOUND] = new OpenApiResponse(sprintf('Journal not found, or invalid %s', implode(' or ', $filters)));
            $paths->addPath($key, $path->withGet($operation->withResponses($responses)));
        }

        return $openApi->withPaths($paths);
    }

    private function hasJournalCodeParameter(Operation $operation): bool
    {
        foreach ($operation->getParameters() ?? [] as $parameter) {
            if (in_array($parameter->getName(), self::JOURNAL_CODE_PARAMETERS, true)) {
                return true;
            }
        }

        return false;
    }

    private function requiresBearerAuth(Operation $operation): bool
    {
        foreach ($operation->getSecurity() ?? [] as $requirement) {
            if (array_key_exists(self::BEARER_AUTH, (array) $requirement)) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------
    // AUTH SCHEMAS
    // -----------------------------------
    private function addAuthSchemas(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();

        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();
        $schemas = $components->getSchemas() ?? new \ArrayObject();

        $securitySchemes[self::BEARER_AUTH] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        $schemas['Credentials'] = $this->credentialsSchema();
        $schemas['Token'] = $this->tokenSchema();
        $schemas['RefreshTokenCredential'] = $this->refreshSchema();

        return $openApi->withComponents(
            $components
                ->withSecuritySchemes($securitySchemes)
                ->withSchemas($schemas)
        );
    }

    // -----------------------------------
    // AUTH ROUTES
    // -----------------------------------
    private function addAuthPaths(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        $paths->addPath(
            self::LOGIN_PATH,
            $this->loginPath()
        );

        $paths->addPath(
            self::REFRESH_PATH,
            $this->refreshPath()
        );

        return $openApi->withPaths($paths);
    }

    // -----------------------------------
    // LOGIN PATH
    // -----------------------------------
    private function loginPath(): PathItem
    {
        return new PathItem(
            post: new Operation(
                operationId: 'postApiLogin',
                tags: [self::OAF_TAGS['auth']],
                responses: $this->tokenResponse('User token created', 'Invalid credentials'),
                summary: 'Get a JWT token',
                description: 'The lifetime of the token is 1 hour',
                requestBody: $this->requestBody('#/components/schemas/Credentials')
            )
        );
    }

    // -----------------------------------
    // REFRESH PATH
    // -----------------------------------
    private function refreshPath(): PathItem
    {
        return new PathItem(
            post: new Operation(
                operationId: 'postApiRefreshToken',
                tags: [self::OAF_TAGS['auth']],
                responses: $this->tokenResponse('User token refreshed', 'Invalid or expired refresh token'),
                summary: 'Refresh a JWT token',
                description: 'The lifetime of the new token will be extended by one month',
                requestBody: $this->requestBody('#/components/schemas/RefreshTokenCredential')
            )
        );
    }

    // -----------------------------------
    // HELPERS
    // -----------------------------------
    private function requestBody(string $ref): RequestBody
    {
        return new RequestBody(
            description: 'Request payload',
            content: new \ArrayObject([
                'application/json' => [
                    'schema' => [
                        '$ref' => $ref,
                    ],
                ],
            ]),
            required: true
        );
    }

    /**
     * @return array<int, OpenApiResponse>
     */
    private function tokenResponse(string $description, string $unauthorizedDescription): array
    {
        return [
            Response::HTTP_OK => new OpenApiResponse(
                description: $description,
                content: new \ArrayObject([
                    'application/json' => new MediaType(
                        schema: new \ArrayObject(['$ref' => '#/components/schemas/Token'])
                    ),
                ])
            ),
            Response::HTTP_UNAUTHORIZED => new OpenApiResponse($unauthorizedDescription),
        ];
    }

    // -----------------------------------
    // SCHEMAS
    // -----------------------------------
    /**
     * @return \ArrayObject<string, mixed>
     */
    private function credentialsSchema(): \ArrayObject
    {
        return new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'example' => 'Your login',
                ],
                'password' => [
                    'type' => 'string',
                    'example' => 'Your API password',
                ],
                'code' => [
                    'type' => ['string', 'null'],
                    'example' => "Journal's code",
                ],
            ],
            'required' => ['username', 'password'],
        ]);
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function tokenSchema(): \ArrayObject
    {
        return new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'token' => [
                    'type' => 'string',
                ],
                'refresh_token' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['token'],
        ]);
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function refreshSchema(): \ArrayObject
    {
        return new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'refresh_token' => [
                    'type' => 'string',
                    'example' => 'd50de0498e38b6489d7....',
                ],
            ],
            'required' => ['refresh_token'],
        ]);
    }
}
