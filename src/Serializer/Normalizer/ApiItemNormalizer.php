<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Symfony\Routing\SkolemIriConverter;
use App\Entity\AbstractVolumeSection;
use App\Entity\EntityIdentifierInterface;
use App\Entity\Paper;
use App\Repository\PaperConflictsRepository;
use App\Repository\PapersRepository;
use App\Repository\SectionRepository;
use App\Repository\VolumeRepository;
use App\Resource\Statistic;
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
            // already set when the papers are not loaded (e.g. the sections assigned to the board members)
            if ($object->getTotalPublishedArticles() === null) {
                $object->setTotalPublishedArticles();
            }
            $data['committee'] = $object->getCommittee();
            $data[PapersRepository::TOTAL_ARTICLE] = $object->getTotalPublishedArticles();
        }

        if ($object instanceof Statistic && is_array($data) && isset($data['@id'])) {
            $data['@id'] = $this->getStatisticIri($object->getName(), $data['@id'], $object->getJournal());
        }

        // Generated IRIs (/.well-known/genid/...) cannot be dereferenced and change on every request: blank nodes instead
        if (is_array($data) && isset($data['@id']) && is_string($data['@id']) && str_contains($data['@id'], strstr(SkolemIriConverter::$skolemUriTemplate, '{id}', true))) {
            unset($data['@id']);
        }

        return $data;
    }

    /**
     * Without an identifier, every statistic gets the IRI of the first item operation (nb-submissions):
     * the IRI is rebuilt from the indicator name (and the journal, for the statistics grouped by journal).
     */
    private function getStatisticIri(string $name, string $defaultIri, ?string $journal = null): string
    {
        $base = dirname($defaultIri); // e.g. /api/statistics

        if (in_array($name, Statistic::AVAILABLE_PUBLICATION_INDICATORS, true)) { // one operation by indicator
            return $journal === null ? sprintf('%s/%s', $base, $name) : sprintf('%s/%s?rvcode=%s', $base, $name, rawurlencode($journal));
        }

        if (in_array($name, Statistic::EVAL_INDICATORS, true)) {
            $iri = sprintf('%s/%s?indicator=%s', $base, Statistic::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection'], $name);

            return $journal === null ? $iri : sprintf('%s&rvcode=%s', $iri, rawurlencode($journal));
        }

        // no dedicated operation (e.g. nb-submissions-details)
        return sprintf('%s/#%s', $base, $name);
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
