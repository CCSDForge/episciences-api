<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Metadata\HttpOperation;
use App\Entity\News;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use App\Repository\RangeInterface;
use App\Resource\Range;
use App\Resource\RangeType;
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
        $journal = null;

        if ($rvCode) {

            $journal = $this->entityManager->getRepository(Review::class)->findOneBy(['code' => $rvCode]);

            if ($journal) {
                $rvId = $journal->getRvid();
            }
        }

        $operationClass = $operation->getClass();
        $identifiers = [];

        if (!empty($filters) && ($operationClass === Volume::class || $operationClass === Section::class) && $operation->getMethod() === 'GET') {

            if ($rvId) {
                $filters['rvid'] = $rvId;
            }

            $identifiers = $this->getIdentifiers($this->entityManager->getRepository($operationClass)->createQueryBuilder('alias'), $filters);
        }

        $this->addHydraContext($data, $operationClass, $journal, $identifiers, $filters);
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

    private function addHydraContext(array &$data, string $operationClass, Review|null $journal, array $identifiers = [], array $filters = []): void
    {

        $repo = null;

        $rvId = $journal?->getRvid();
        $rvCode = $journal?->getCode();

        if($operationClass === News::class || $operationClass === Volume::class){
            $repo = $this->entityManager->getRepository($operationClass);
        }

        $hydraMember = $data['hydra:member'] ?? [];
        if ($operationClass === News::class) {
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['years' => $repo ? (new Range())->setYears($repo->getRange($rvCode))->getYears() : []];
        } elseif ($operationClass === Volume::class || $operationClass === Section::class) {
            $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = 0;

            if($operationClass === Volume::class){

                $rangeType = (new RangeType())
                    ->setTypes($repo->getTypes($rvId))
                    ->setYears($repo->getRange($rvId));

                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['year' => $rangeType->getYears(), 'types' => $rangeType->getTypes()];

            }

            if (!empty($hydraMember)) {
                $allPublishedArticles = $this->entityManager->getRepository(Paper::class)->getTotalArticleBySectionOrVolume($operationClass, Paper::STATUS_PUBLISHED, $identifiers, $rvId, $filters);
                $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = $allPublishedArticles[PapersRepository::TOTAL_ARTICLE];
            }

        }
        asort($data);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {

        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }

    }
}