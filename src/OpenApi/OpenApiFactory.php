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

    public const string NOTE_PAPERS_MANAGE = 'Champ confidentiel : visible uniquement par les utilisateurs autorisés à gérer l\'article (voter `papers_manage`).';
    public const string NOTE_ROLE_SECRETARY = 'Champ confidentiel : visible uniquement par les secrétaires de rédaction (ROLE_SECRETARY).';
    public const string NOTE_ROLE_MEMBER = 'Champ confidentiel : visible uniquement par les membres authentifiés (ROLE_MEMBER).';
    public const string NOTE_ROLE_EPIADMIN = 'Champ confidentiel : visible uniquement par les administrateurs EPI (ROLE_EPIADMIN).';

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
     * The opening security (`bearerAuth`) is voluntarily not enforced (the operation stays
     * public): the JWT is optional. This method only documents that behaviour.
     */
    private function documentOptionalAuthMode(OpenApi $openApi): OpenApi
    {
        /** @var array<string, string> $paths URI path -> human label */
        $paths = $this->getOptionalAuthPaths();

        foreach ($paths as $pathKey => $label) {
            $path = $openApi->getPaths()->getPath($pathKey);
            $get = $path?->getGet();

            if (!$get instanceof Operation) {
                continue;
            }

            $description = trim((string)$get->getDescription());
            $note = 'JWT optionnel : l\'accès est public par défaut, mais la fourniture d\'un token étend ou restreint le périmètre renvoyé selon le rôle (mode connecté).';

            $get = $get->withDescription($description !== '' ? $description . ' ' . $note : $note);

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
            $prefix = explode('.', (string)$name)[0];
            $sensitive = self::SENSITIVE_FIELDS[$prefix] ?? null;
            if ($sensitive === null) {
                continue;
            }

            // Schemas are stored as (possibly nested) \ArrayObject; work on a plain array.
            $schemaArray = json_decode((string)json_encode($schema), true);
            if (!\is_array($schemaArray)) {
                continue;
            }

            $updated = $this->annotateSensitiveProperties($schemaArray, $sensitive);
            if ($updated !== $schemaArray) {
                $schemas[$name] = new \ArrayObject($updated);
            }
        }

        return $openApi->withComponents($components->withSchemas($schemas));
    }

    /**
     * Recursively annotates sensitive properties declared in a schema (handles
     * both top-level `properties` and collections of `allOf`/`oneOf`/`anyOf`).
     *
     * @param  array<array-key, mixed> $schema
     * @param  array<string, string>   $sensitive Property name -> note
     * @return array<array-key, mixed>
     */
    private function annotateSensitiveProperties(array $schema, array $sensitive): array
    {
        if (isset($schema['properties']) && \is_array($schema['properties'])) {
            foreach ($schema['properties'] as $property => $definition) {
                if (!\is_array($definition)) {
                    continue;
                }

                $note = $sensitive[$property] ?? null;
                if ($note === null) {
                    continue;
                }

                $current = isset($definition['description']) && \is_string($definition['description'])
                    ? rtrim($definition['description'], ' ')
                    : '';

                $definition['description'] = $current !== '' ? $current . ' ' . $note : $note;
                $schema['properties'][$property] = $definition;
            }
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $combinator) {
            if (isset($schema[$combinator]) && \is_array($schema[$combinator])) {
                foreach ($schema[$combinator] as $index => $subSchema) {
                    if (\is_array($subSchema)) {
                        $schema[$combinator][$index] = $this->annotateSensitiveProperties($subSchema, $sensitive);
                    }
                }
            }
        }

        return $schema;
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
