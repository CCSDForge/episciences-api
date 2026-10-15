<?php

namespace App\Serializer\Normalizer;

use App\Entity\AbstractVolumeSection;
use App\Entity\EntityIdentifierInterface;
use App\Entity\Paper;
use App\Repository\PaperConflictsRepository;
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

class ApiItemNormalizer implements NormalizerInterface, SerializerAwareInterface
{

    private SerializerInterface $serializer;

    public function __construct(
        private readonly NormalizerInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaperConflictsRepository $paperConflictsRepository,
    ) {

    }


    /**
     * @throws ReflectionException
     * @throws ExceptionInterface
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        if (
            $object instanceof Paper &&
            is_array($data) &&
            array_key_exists('conflicts', $data) &&
            $object->getPaperid() !== null &&
            $object->getConflicts()->isEmpty()
        ) {
            $object->setConflicts($this->paperConflictsRepository->findByPaperId($object->getPaperid()));
            $data['conflicts'] = $this->serializer->normalize($object->getConflicts(), $format, $context);
        }

        if (
            $object instanceof AbstractVolumeSection &&
            is_array($data) &&
            new ReflectionClass($object::class)->implementsInterface(EntityIdentifierInterface::class)
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
        $this->serializer = $serializer;

        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
