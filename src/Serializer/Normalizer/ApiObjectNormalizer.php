<?php

namespace App\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorates the JSON-LD normalizer of the objects that are not API resources (e.g. the user roles embedded
 * in a user), which gives them a generated IRI.
 */
class ApiObjectNormalizer implements NormalizerInterface
{
    use SkolemIriRemoverTrait;

    public function __construct(private readonly NormalizerInterface $decorated)
    {
    }

    /**
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $this->removeSkolemIri($this->decorated->normalize($data, $format, $context));
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
