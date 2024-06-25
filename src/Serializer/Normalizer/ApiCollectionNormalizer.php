<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Metadata\HttpOperation;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use App\Traits\QueryTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiCollectionNormalizer extends AbstractNormalizer implements NormalizerInterface, NormalizerAwareInterface, SerializerAwareInterface
{
    use QueryTrait;

    public const FORMAT = 'jsonld';

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->decorated->setNormalizer($normalizer);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        $hydraMember = $data['hydra:member'] ?? [];

        if (empty($hydraMember)) {
            return $data;
        }

        /** @var HttpOperation $operation */
        $operation = $context['operation'] ?? $context['root_operation'] ?? null;

        if (!$operation) {
            return null;
        }

        $requestUri = $context['uri'] ?? '';

        parse_str($requestUri, $parsedUri);

        $filters = [];

        if (isset($parsedUri['year'])) {
            $filters['year'] = $parsedUri['year'];
        }

        if (isset($parsedUri['type'])) {
            $filters['type'] = $parsedUri['type'];
        }

        if (isset($parsedUri['vid'])) {
            $filters['vid'] = $parsedUri['vid'];
        }

        $rvCode = $parsedUri['rvcode'] ?? null;

        $rvId = null;

        if ($rvCode) {

            $journal = $this->entityManager->getRepository(Review::class)->findOneBy(['code' => $rvCode]);

            if ($journal) {
                $rvId = $journal->getRvid();
            }
        }


        $operationClass = $operation->getClass();

        if (($operationClass === Volume::class || $operationClass === Section::class) && $operation->getMethod() === 'GET') {

            $identifiers = [];

            if (!empty($filters)) {

                if ($rvId) {
                    $filters['rvid'] = $rvId;
                }

                $identifiers = $this->getIdentifiers($this->entityManager->getRepository($operationClass)->createQueryBuilder('alias'), $filters);
            }

            foreach ($hydraMember as $index => $values) {
                $id = $operationClass === Volume::class ? $values['vid'] : $values['sid'];
                $hydraMember[$index]['committee'] = $this->entityManager->getRepository($operationClass)->getCommittee($values['rvid'], $id);
                $hydraMember[$index][PapersRepository::TOTAL_ARTICLE] = count($values['papers']) ?? 0;
            }

            if (!empty($hydraMember)) {
                $this->addHydraContext($data, $operationClass, $rvId, $identifiers, $filters);
            } else {
                $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = 0;
            }
        }

        $data['hydra:member'] = $hydraMember;
        return $data;

    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => self::FORMAT];
    }

    private function addHydraContext(array &$data, string $operationClass, $rvId, array $identifiers = [], array $filters = []): void
    {
        $allPublishedArticles = $this->entityManager->getRepository(Paper::class)->getTotalArticleBySectionOrVolume($operationClass, Paper::STATUS_PUBLISHED, $identifiers, $rvId, $filters);
        $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = $allPublishedArticles[PapersRepository::TOTAL_ARTICLE];
        asort($data);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {

        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }

    }
}