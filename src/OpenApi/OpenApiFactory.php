<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
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

    public function __construct(
        private OpenApiFactoryInterface $decorated
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi = $this->filterHiddenEndpoints($openApi);

        $openApi = $this->addAuthSchemas($openApi);

        $openApi = $this->addAuthPaths($openApi);

        $openApi = $this->documentUnpaginatedCap($openApi);

        return $openApi;
    }

    // -----------------------------------
    // CAP OF THE UNPAGINATED COLLECTIONS
    // -----------------------------------
    /**
     * Documents, on the collections that can be requested without pagination, the cap
     * applied to ?pagination=false and the TRUNCATED_HEADER response header.
     */
    private function documentUnpaginatedCap(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $key => $path) {
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
    // AUTH SCHEMAS
    // -----------------------------------
    private function addAuthSchemas(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();

        $securitySchemes = $components->getSecuritySchemes();
        $schemas = $components->getSchemas();

        $securitySchemes['bearerAuth'] = new \ArrayObject([
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
                responses: $this->tokenResponse('User token created'),
                summary: '',
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
                responses: $this->tokenResponse('User token refreshed'),
                summary: '',
                description: 'The lifetime of the new token will be extended by one month',
                requestBody: $this->requestBody('#/components/schemas/RefreshTokenCredential'),
                security: [
                    ['bearerAuth' => []],
                ]
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
            ])
        );
    }

    private function tokenResponse(string $description): array
    {
        return [
            Response::HTTP_OK => [
                'description' => $description,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Token',
                        ],
                    ],
                ],
            ],
        ];
    }

    // -----------------------------------
    // SCHEMAS
    // -----------------------------------
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
                    'type' => 'string',
                    'example' => "Journal's code",
                    'nullable' => true,
                ],
            ],
            'required' => ['username', 'password'],
        ]);
    }

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
