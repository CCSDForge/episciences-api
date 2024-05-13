<?php

namespace App\Serializer;


use App\Entity\Paper;
use App\Service\MetadataSources;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiNormalizer implements NormalizerInterface, SerializerAwareInterface
{

    private MetadataSources $metadataSourcesService;

    public function __construct(private readonly NormalizerInterface $decorated, MetadataSources $metadataSourcesService)
    {
        $this->metadataSourcesService = $metadataSourcesService;
    }


    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        // duplication of information: now included in Paper::document


//        if (($object instanceof Papers) && is_array($data)) {
//            $data['statusLabel'] = $object->getStatusDictionaryLabel();
//            $data['repository'] = $this->metadataSourcesService->repositoryToArray($object->getRepoid());
//        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}


