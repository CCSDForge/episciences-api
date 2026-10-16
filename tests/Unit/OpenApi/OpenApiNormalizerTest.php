<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenApi;

use App\OpenApi\OpenApiNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OpenApiNormalizerTest extends TestCase
{
    public function testUnreferencedSchemasAreRemoved(): void
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn([
            'paths' => [
                '/api/papers' => [
                    'get' => [
                        'responses' => [
                            '200' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Paper']]]],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => new \ArrayObject([
                    'Paper' => ['properties' => ['volume' => ['$ref' => '#/components/schemas/Volume']]],
                    'Volume' => ['type' => 'object'],
                    'User' => ['properties' => ['roles' => ['$ref' => '#/components/schemas/UserRoles']]],
                    'UserRoles' => ['type' => 'object'],
                ]),
            ],
        ]);

        $openApi = (new OpenApiNormalizer($decorated))->normalize(new \stdClass());

        $this->assertSame(['Paper', 'Volume'], array_keys($openApi['components']['schemas']->getArrayCopy()));
    }

    public function testDocumentWithoutSchemasIsUnchanged(): void
    {
        $document = ['paths' => [], 'components' => []];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn($document);

        $this->assertSame($document, (new OpenApiNormalizer($decorated))->normalize(new \stdClass()));
    }
}
