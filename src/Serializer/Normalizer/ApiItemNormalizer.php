<?php

namespace App\Serializer\Normalizer;

use App\Entity\AbstractVolumeSection;
use App\Entity\EntityIdentifierInterface;
use App\Repository\PapersRepository;
use App\Repository\SectionRepository;
use App\Repository\VolumeRepository;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

readonly class ApiItemNormalizer implements NormalizerInterface, SerializerAwareInterface
{

    public function __construct(private NormalizerInterface $decorated, private EntityManagerInterface $entityManager)
    {

    }


    /**
     * @throws ReflectionException
     * @throws ExceptionInterface
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        if (
            $object instanceof AbstractVolumeSection &&
            is_array($data) &&
            (new ReflectionClass($object::class))->implementsInterface(EntityIdentifierInterface::class)
        ) {
            /** @var SectionRepository | VolumeRepository $currentRepo */
            $currentRepo = $this->entityManager->getRepository($object::class);
            $committee = $currentRepo->getCommittee($object->getRvid(), $object->getIdentifier());
            $object->setCommittee($committee);
            $object->setTotalPublishedArticles();
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
