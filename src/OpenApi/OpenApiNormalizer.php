<?php

declare(strict_types=1);

namespace App\OpenApi;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Removes component schemas that are not referenced, directly or transitively, by any path
 * (e.g. schemas generated for operations whose responses are documented manually).
 */
final readonly class OpenApiNormalizer implements NormalizerInterface
{
    private const string SCHEMA_REF_PREFIX = '#/components/schemas/';

    public function __construct(
        private NormalizerInterface $decorated
    ) {}

    /**
     * @return array<mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $openApi = $this->decorated->normalize($data, $format, $context);

        return is_array($openApi) ? $this->removeUnusedSchemas($openApi) : $openApi;
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
