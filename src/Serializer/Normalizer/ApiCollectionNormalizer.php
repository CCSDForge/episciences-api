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
use App\Resource\Browse;
use App\Resource\Range;
use App\Resource\RangeType;
use App\Resource\Statistic;
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
        /** @var HttpOperation $operation */
        $operation = $context['operation'] ?? $context['root_operation'] ?? null;

        if (!$operation) {
            return null;
        }

        $operationClass = $operation->getClass();

        $filters = [];
        $journal = null;

        $requestUri = $context['uri'] ?? '';
        parse_str($requestUri, $parsedUri);

        $rvCode = $parsedUri['rvcode'] ?? null;
        $isOnlyAccepted = isset($parsedUri['only_accepted']) && filter_var($parsedUri['only_accepted'], FILTER_VALIDATE_BOOLEAN);

        if ($rvCode) {
            $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($rvCode);
        }

        $hydraMember = $data['hydra:member'] ?? [];


        if (empty($hydraMember)) {
            $this->addHydraContext($data, $operationClass, $journal, $filters);
            return $data;
        }

        $identifiers = [];
        $rvId = $journal?->getRvid();

        if (isset($parsedUri['year'])) {
            $filters['year'] = $parsedUri['year'];
        }

        if (isset($parsedUri['type'])) {
            $filters['type'] = $parsedUri['type'];
        }

        if (isset($parsedUri['vid'])) {
            $filters['vid'] = $parsedUri['vid'];
        }

        if ($isOnlyAccepted) {
            $filters['isOnlyAccepted'] = true;
        }

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

        if (!isset($data['hydra:totalItems'])) { // is not a collection, so don't add context
            return;
        }

        $repo = null;

        $rvId = $journal?->getRvid();
        $rvCode = $journal?->getCode();

        $id = $data['@id'] ?? null;

        if ($operationClass === News::class || $operationClass === Volume::class | $operationClass === Paper::class) {
            $repo = $this->entityManager->getRepository($operationClass);
        }

        if($operationClass === Browse::class){

            if($id  === Browse::BROWSE_AUTHORS_COLLECTION_IDENTIFIER){
                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $this->solrService->getCountArticlesByAuthorsFirstLetter();
            }

        }elseif ($operationClass === Statistic::class) {
            $repo = $this->entityManager->getRepository(Paper::class);
            $years = $repo->getYearRange($rvId);
            rsort($years);
            $range['years'] = $years;

            if ($id === Statistic::STATISTIC_GET_COLLECTION_OPERATION_IDENTIFIER) {
                $range['indicators'] = array_values(Statistic::AVAILABLE_PUBLICATION_INDICATORS);

            } elseif ($id === Statistic::EVALUATION_GET_COLLECTION_OPERATION_IDENTIFIER) {
                $range['indicators'] = array_values(Statistic::EVAL_INDICATORS);
            }

            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $range;

        } elseif ($operationClass === Paper::class) {

            $isOnlyAccepted = $filters['isOnlyAccepted'] ?? false;
            $rangeType = (new RangeType())
                ->setTypes($repo->getTypes(['rvid' => $rvId, 'isOnlyAccepted' => $isOnlyAccepted]));

            if (!$isOnlyAccepted) {
                $rangeType->setYears($repo->getRange(['rvid' => $rvId]));
                $range = ['publicationYears' => $rangeType->getYears()];
            }

            $range['types'] = $rangeType->getTypes();
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $range;

        } elseif ($operationClass === News::class) {
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['years' => $repo ? (new Range())->setYears($repo->getRange($rvCode))->getYears() : []];
        } elseif ($operationClass === Volume::class || $operationClass === Section::class) {
            $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = 0;

            if ($operationClass === Volume::class) {

                $rangeType = (new RangeType())
                    ->setTypes($repo->getTypes($rvId))
                    ->setYears($repo->getRange($rvId));

                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['year' => $rangeType->getYears(), 'types' => $rangeType->getTypes()];

            }

            if (!empty($data['hydra:member'])) {
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