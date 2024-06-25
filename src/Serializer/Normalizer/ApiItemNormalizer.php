<?php

namespace App\Serializer\Normalizer;

use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiItemNormalizer implements NormalizerInterface, SerializerAwareInterface
{

    public function __construct(private readonly NormalizerInterface $decorated, private readonly EntityManagerInterface $entityManager)
    {

    }


    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        if ((($isVolume = $object instanceof Volume) || $object instanceof Section) && is_array($data)) {
            $data['committee'] = $this->entityManager->getRepository($object::class)->getCommittee($object->getRvid(), $isVolume ? $object->getVid() : $object->getSid());
            $data[PapersRepository::TOTAL_ARTICLE] = count($data['papers']) ?? 0;
        }

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