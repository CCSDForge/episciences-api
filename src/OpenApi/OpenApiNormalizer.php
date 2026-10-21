<?php

declare(strict_types=1);

namespace App\OpenApi;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * - Documents the properties added to the JSON-LD output by the custom serializer normalizers.
 * - Removes component schemas that are not referenced, directly or transitively, by any path
 *   (e.g. schemas generated for operations whose responses are documented manually).
 */
final readonly class OpenApiNormalizer implements NormalizerInterface
{
    private const string SCHEMA_REF_PREFIX = '#/components/schemas/';

    private const string JSONLD = 'application/ld+json';

    /** JSON-LD schemas of the volumes and sections */
    private const string VOLUME_SECTION_JSONLD_SCHEMA = '/^(Volume|Section)\.jsonld-/';

    /** Added to each volume and section by App\Serializer\Normalizer\ApiItemNormalizer */
    private const array VOLUME_SECTION_JSONLD_PROPERTIES = [
        'committee' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'uuid' => ['type' => 'string', 'format' => 'uuid'],
                    'civ' => ['type' => ['string', 'null']],
                    'screenName' => ['type' => 'string'],
                    'orcid' => ['type' => ['string', 'null']],
                ],
            ],
        ],
        'totalPublishedArticles' => ['type' => 'integer'],
    ];

    /**
     * Added to the collections of volumes and sections by App\Serializer\Normalizer\ApiCollectionNormalizer.
     * The "hydra:" prefix (here and in collectionMemberRef()) requires api_platform.serializer.hydra_prefix: true,
     * as the normalizers of App\Serializer\Normalizer do (see ApiPlatformConfigTest).
     */
    private const array VOLUME_SECTION_JSONLD_COLLECTION_PROPERTIES = [
        'hydra:totalPublishedArticles' => ['type' => ['integer', 'null']],
    ];

    public function __construct(
        private NormalizerInterface $decorated
    ) {}

    /**
     * @return array<mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $openApi = $this->decorated->normalize($data, $format, $context);

        return is_array($openApi) ? $this->removeUnusedSchemas($this->addJsonLdOnlyProperties($openApi)) : $openApi;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    /**
     * @param array<string, mixed> $openApi
     *
     * @return array<string, mixed>
     */
    private function addJsonLdOnlyProperties(array $openApi): array
    {
        foreach ($openApi['components']['schemas'] ?? [] as $name => $schema) {
            if (preg_match(self::VOLUME_SECTION_JSONLD_SCHEMA, (string) $name)) {
                $openApi['components']['schemas'][$name] = $this->addProperties($schema, self::VOLUME_SECTION_JSONLD_PROPERTIES);
            }
        }

        foreach ($openApi['paths'] ?? [] as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                $memberRef = $this->collectionMemberRef($operation['responses']['200']['content'][self::JSONLD]['schema'] ?? null);

                if ($memberRef === null || !preg_match(self::VOLUME_SECTION_JSONLD_SCHEMA, substr($memberRef, strlen(self::SCHEMA_REF_PREFIX)))) {
                    continue;
                }

                // From the current path item: several methods of the same path may be updated
                $openApi['paths'][$path] = $this->updateIn(
                    $openApi['paths'][$path],
                    [$method, 'responses', '200', 'content', self::JSONLD, 'schema'],
                    fn(array|\ArrayObject $schema): array|\ArrayObject => $this->addProperties($schema, self::VOLUME_SECTION_JSONLD_COLLECTION_PROPERTIES)
                );
            }
        }

        return $openApi;
    }

    /**
     * Reference of the member schema of a hydra collection schema
     */
    private function collectionMemberRef(mixed $schema): ?string
    {
        foreach ($schema['allOf'] ?? [] as $part) {
            $ref = $part['properties']['hydra:member']['items']['$ref'] ?? null;

            if (is_string($ref)) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * Adds properties to an object schema, or to the parts of an "allOf" schema that declare properties
     *
     * @param array<string, mixed>|\ArrayObject<string, mixed> $schema
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>|\ArrayObject<string, mixed>
     */
    private function addProperties(array|\ArrayObject $schema, array $properties): array|\ArrayObject
    {
        if (isset($schema['allOf'])) {
            $allOf = $schema['allOf'];

            foreach ($allOf as $index => $part) {
                if (isset($part['properties'])) {
                    $allOf[$index] = $this->addProperties($part, $properties);
                }
            }

            $schema['allOf'] = $allOf;

            return $schema;
        }

        $existing = $schema['properties'] ?? [];
        $schema['properties'] = [...($existing instanceof \ArrayObject ? $existing->getArrayCopy() : $existing), ...$properties];

        return $schema;
    }

    /**
     * Replaces the value found by following the keys, keeping the arrays and \ArrayObject of the document
     *
     * @param array<array-key, mixed>|\ArrayObject<array-key, mixed> $node
     * @param string[] $keys
     *
     * @return array<array-key, mixed>|\ArrayObject<array-key, mixed>
     */
    private function updateIn(array|\ArrayObject $node, array $keys, callable $update): array|\ArrayObject
    {
        $key = array_shift($keys);

        if (!isset($node[$key])) {
            return $node;
        }

        $node[$key] = $keys === [] ? $update($node[$key]) : $this->updateIn($node[$key], $keys, $update);

        return $node;
    }

    /**
     * @param array<string, mixed> $openApi
     *
     * @return array<string, mixed>
     */
    private function removeUnusedSchemas(array $openApi): array
    {
        $schemas = $openApi['components']['schemas'] ?? null;

        if ($schemas instanceof \ArrayObject) {
            $schemas = $schemas->getArrayCopy();
        }

        if (!is_array($schemas)) {
            return $openApi;
        }

        $withoutSchemas = $openApi;
        unset($withoutSchemas['components']['schemas']);

        $used = [];
        $queue = $this->collectSchemaRefs($withoutSchemas);

        while ($queue !== []) {
            $name = array_pop($queue);

            if (isset($used[$name])) {
                continue;
            }

            $used[$name] = true;

            if (isset($schemas[$name])) {
                array_push($queue, ...$this->collectSchemaRefs($schemas[$name]));
            }
        }

        $openApi['components']['schemas'] = new \ArrayObject(array_intersect_key($schemas, $used));

        return $openApi;
    }

    /**
     * @return string[] referenced schema names
     */
    private function collectSchemaRefs(mixed $node): array
    {
        if (!is_iterable($node)) {
            return [];
        }

        $refs = [];

        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, self::SCHEMA_REF_PREFIX)) {
                $refs[] = substr($value, strlen(self::SCHEMA_REF_PREFIX));
                continue;
            }

            array_push($refs, ...$this->collectSchemaRefs($value));
        }

        return $refs;
    }
}
