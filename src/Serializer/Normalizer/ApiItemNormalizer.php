<?php

namespace App\Serializer\Normalizer;

use App\Entity\AbstractVolumeSection;
use App\Entity\EntityIdentifierInterface;
use App\Entity\User;
use App\Repository\PapersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiItemNormalizer implements NormalizerInterface, SerializerAwareInterface
{

    public function __construct(private readonly NormalizerInterface $decorated, private readonly EntityManagerInterface $entityManager, private ParameterBagInterface $parameters)
    {

    }


    /**
     * @throws \ReflectionException
     * @throws ExceptionInterface
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        if (
            $object instanceof AbstractVolumeSection &&
            is_array($data) &&
            (new \ReflectionClass($object::class))->implementsInterface(EntityIdentifierInterface::class)
        ) {
            $committee = $this->entityManager->getRepository($object::class)->getCommittee($object->getRvid(), $object->getIdentifier());
            $object->setCommittee($committee);
            $object->setTotalPublishedArticles(count($data['papers'] ?? []) ?? 0);
            $data['committee'] = $object->getCommittee();
            $data[PapersRepository::TOTAL_ARTICLE] = $object->getTotalPublishedArticles();
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