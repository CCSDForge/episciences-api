<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\HttpFoundation\Response;

class OpenApiFactory implements OpenApiFactoryInterface
{
    public const OAF_HIDDEN = 'hidden';
    public const OAF_TAGS = [
        'auth' => 'Sign in - Myspace',
        'stats' => 'Statistics',
    ];
    public const JWT_POST_LOGIN_OPERATION_ID = 'login_check_post';

    private OpenApiFactoryInterface $decorated;

    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        /** @var PathItem $path */

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
                ]
            ],
            'required' => ['token'],
        ]);

        $pathItem = new PathItem(
            null,
            '',
            '',
            null,
            null,
            new Operation(
                'postApiLogin',
                [self::OAF_TAGS['auth']],
                [
                    Response::HTTP_OK => [
                        'description' => 'User token created',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#components/schemas/Token'
                                ]
                            ]
                        ]
                    ]

                ],
                '',
                '',
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

        return $openApi;
    }
}