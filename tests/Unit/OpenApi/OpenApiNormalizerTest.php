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

    public function testJsonLdOnlyPropertiesOfVolumesAndSectionsAreDocumented(): void
    {
        $collectionSchema = static fn(string $member): array => [
            'type' => 'object',
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraCollectionBaseSchema'],
                ['type' => 'object', 'properties' => ['hydra:member' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/' . $member]]]],
            ],
        ];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn([
            'paths' => [
                '/api/volumes' => [
                    'get' => [
                        'responses' => [
                            '200' => ['content' => [
                                'application/ld+json' => ['schema' => $collectionSchema('Volume.jsonld-read.Volumes')],
                                'application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Volume-read.Volumes']]],
                            ]],
                        ],
                    ],
                ],
                '/api/news' => [
                    'get' => [
                        'responses' => [
                            '200' => ['content' => ['application/ld+json' => ['schema' => $collectionSchema('News.jsonld-read.News')]]],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => new \ArrayObject([
                    'HydraCollectionBaseSchema' => ['type' => 'object'],
                    'Volume.jsonld-read.Volumes' => new \ArrayObject([
                        'allOf' => [
                            ['$ref' => '#/components/schemas/HydraCollectionBaseSchema'],
                            ['type' => 'object', 'properties' => ['vid' => ['type' => 'integer']]],
                        ],
                    ]),
                    'Volume-read.Volumes' => ['type' => 'object', 'properties' => ['vid' => ['type' => 'integer']]],
                    'News.jsonld-read.News' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ]),
            ],
        ]);

        $openApi = (new OpenApiNormalizer($decorated))->normalize(new \stdClass());
        $schemas = $openApi['components']['schemas'];

        $this->assertSame(['vid', 'committee', 'totalPublishedArticles'], array_keys($schemas['Volume.jsonld-read.Volumes']['allOf'][1]['properties']));
        $this->assertSame(['vid'], array_keys($schemas['Volume-read.Volumes']['properties']));
        $this->assertSame(['id'], array_keys($schemas['News.jsonld-read.News']['properties']));

        $volumesContent = $openApi['paths']['/api/volumes']['get']['responses']['200']['content'];
        $this->assertArrayHasKey('hydra:totalPublishedArticles', $volumesContent['application/ld+json']['schema']['allOf'][1]['properties']);
        $this->assertArrayNotHasKey('properties', $volumesContent['application/json']['schema']);

        $newsSchema = $openApi['paths']['/api/news']['get']['responses']['200']['content']['application/ld+json']['schema'];
        $this->assertArrayNotHasKey('hydra:totalPublishedArticles', $newsSchema['allOf'][1]['properties']);
    }

    /**
     * Each method of a path is updated from the current path item: the previous updates are kept.
     */
    public function testJsonLdCollectionPropertiesAreAddedToEveryMethodOfAPath(): void
    {
        $operation = static fn(): array => [
            'responses' => [
                '200' => ['content' => ['application/ld+json' => ['schema' => [
                    'type' => 'object',
                    'allOf' => [
                        ['$ref' => '#/components/schemas/HydraCollectionBaseSchema'],
                        ['type' => 'object', 'properties' => ['hydra:member' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Volume.jsonld-read.Volumes']]]],
                    ],
                ]]]],
            ],
        ];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn([
            'paths' => ['/api/volumes' => ['get' => $operation(), 'post' => $operation()]],
            'components' => [
                'schemas' => new \ArrayObject([
                    'HydraCollectionBaseSchema' => ['type' => 'object'],
                    'Volume.jsonld-read.Volumes' => ['type' => 'object', 'properties' => ['vid' => ['type' => 'integer']]],
                ]),
            ],
        ]);

        $openApi = (new OpenApiNormalizer($decorated))->normalize(new \stdClass());

        foreach (['get', 'post'] as $method) {
            $schema = $openApi['paths']['/api/volumes'][$method]['responses']['200']['content']['application/ld+json']['schema'];
            $this->assertArrayHasKey('hydra:totalPublishedArticles', $schema['allOf'][1]['properties'], $method);
        }
    }

    public function testDocumentWithoutSchemasIsUnchanged(): void
    {
        $document = ['paths' => [], 'components' => []];

        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn($document);

        $this->assertSame($document, (new OpenApiNormalizer($decorated))->normalize(new \stdClass()));
    }
}
