<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

class OpenApiFactory implements OpenApiFactoryInterface
{
    public const OAF_HIDDEN = 'hidden';
    public const OAF_TAGS = [
        'auth' => 'Sign in - Myspace',
        'stats' => 'Statistics',
        'review' => 'Journals',
        'user' => 'User'
    ];
    public const JWT_POST_LOGIN_OPERATION_ID = 'login_check_post';
    public const USER_GET_COLLECTION_PATH = '/api/users';

    private OpenApiFactoryInterface $decorated;

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        return $this->applyToDefaultEndPoints(
            $this->applyToCustomEndPoints(
                $this->decorated->__invoke($context)
            )
        );
    }


    private function outputToken(string $description): array
    {
        return [
            Response::HTTP_OK => [
                'description' => $description,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#components/schemas/Token'
                        ]
                    ]
                ]
            ]
        ];
    }


    private function applyToDefaultEndPoints(OpenApi $openApi): OpenApi
    {
        //todo see how to rename this filter 'roles.rvid' in 'users' end point
        return $openApi;
    }


    private function applyToCustomEndPoints(OpenApi $openApi): OpenApi
    {
        foreach ($openApi->getPaths()->getPaths() as $key => $path) {

            if ($path->getGet() && ($path->getGet()->getSummary() === self::OAF_HIDDEN)) {
                $openApi->getPaths()->addPath($key, $path->withGet(null));
            }

            if ($path->getPost() && ($path->getPost()->getOperationId() === self::JWT_POST_LOGIN_OPERATION_ID)) {
                $openApi->getPaths()->addPath($key, $path->withPost(null));
            }

        }

        $schemas = $openApi->getComponents()->getSecuritySchemes();

        $schemas['bearerAuth'] = new \ArrayObject([
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT'
            ]
        );

        $schemas = $openApi->getComponents()->getSchemas();

        $schemas['Credentials'] = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'example' => 'Your login',
                    'nullable' => false,
                ],

                'password' => [
                    'type' => 'string',
                    'example' => 'Your API password',
                    'nullable' => false,
                ],

                'code' => [
                    'type' => 'string',
                    'example' => "Journal's code",
                    'nullable' => true,
                ]

            ],
            'required' => ['username', 'password'],
        ]);

        $schemas['Token'] = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'token' => [
                    'type' => 'string',
                    'readOnly' => true,
                    'nullable' => false,
                ],
                'refresh_token' => [
                    'type' => 'string',
                    'readOnly' => true,
                    'nullable' => false,
                ]
            ],
            'required' => ['token'],
        ]);


        $schemas['RefreshTokenCredential'] = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'refresh_token' => [
                    'type' => 'string',
                    'example' => 'd50de0498e38b6489d7....',
                    'nullable' => false,
                ],
            ],
            'required' => ['refresh_token'],
        ]);


        // login path item
        $pathItem = new PathItem(
            null,
            '',
            '',
            null,
            null,
            new Operation(
                'postApiLogin',
                [self::OAF_TAGS['auth']],
                $this->outputToken('User token created'),
                '',
                'The lifetime of the token is 1 hour',
                null,
                [],
                new RequestBody(
                    'The login data',
                    new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#components/schemas/Credentials'
                                ]
                            ]
                        ]
                    )
                )
            )
        );

        $openApi->getPaths()->addPath('/api/login', $pathItem);

        // refresh token path item
        $pathItem = new PathItem(
            null,
            '',
            '',
            null,
            null,
            new Operation(
                'postApiRefreshToken',
                [self::OAF_TAGS['auth']],
                $this->outputToken('User token refreshed'),
                '',
                'The lifetime of the new token will be extended by one month',
                null,
                [],
                new RequestBody(
                    'The login data',
                    new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#components/schemas/RefreshTokenCredential'
                                ]
                            ]
                        ]
                    )
                ),
                security: [
                    ['bearerAuth' => []]
                ]
            )
        );

        $openApi->getPaths()->addPath('/api/token/refresh', $pathItem);

        return $openApi;

    }
}