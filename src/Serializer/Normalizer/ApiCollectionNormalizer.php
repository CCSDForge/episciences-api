<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Metadata\HttpOperation;
use App\Entity\News;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\Section;
use App\Entity\Volume;
use App\Repository\PapersRepository;
use App\Repository\RangeInterface;
use App\Repository\ReviewRepository;
use App\Resource\Browse;
use App\Resource\Range;
use App\Resource\RangeType;
use App\Resource\Search;
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
            /** @var ReviewRepository $journalRepo */
            $journalRepo = $this->entityManager->getRepository(Review::class);
            $journal = $journalRepo->getJournalByIdentifier($rvCode);

            if ($operationClass === Volume::class){
                $filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] = (bool)$journal->getSetting(ReviewSetting::DISPLAY_EMPTY_VOLUMES);
            }

        }

        $hydraMember = $data['hydra:member'] ?? [];

        $filters['isGranted'] = $this->security->isGranted('ROLE_SECRETARY');

        if ($operationClass === Search::class && $parsedUri[Search::TERMS_PARAM]) {
            $filters[Search::TERMS_PARAM] = $parsedUri[Search::TERMS_PARAM];
        }


        if (empty($hydraMember)) {
            $this->addHydraContext($data, $operationClass, $journal, [], $filters);
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

        if (
            !empty($filters) &&
            ($operationClass === Volume::class || $operationClass === Section::class) &&
            $operation->getMethod() === 'GET'
        ) {

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

        if ($operationClass === Browse::class) {

            if ($id === Browse::BROWSE_AUTHORS_COLLECTION_IDENTIFIER) {
                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $this->solrService->getCountArticlesByAuthorsFirstLetter();
            }

        } elseif ($operationClass === Statistic::class) {
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

                $isDisplayEmptyVolume = $filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false;
                $onlyPublished = !isset($filters['isGranted']) || !$filters['isGranted']; // FALSE IF GRANTED SECRETARY

                $rangeType = (new RangeType())
                    ->setTypes($repo->getTypes($rvId, $isDisplayEmptyVolume, $onlyPublished))
                    ->setYears($repo->getRange($rvId, $isDisplayEmptyVolume, $onlyPublished));

                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['year' => $rangeType->getYears(), 'types' => $rangeType->getTypes()];

            }

            if (!empty($data['hydra:member'])) {
                /** @var PapersRepository $paperRepo */
                $paperRepo = $this->entityManager->getRepository(Paper::class);
                $allPublishedArticles = $paperRepo->getTotalArticleBySectionOrVolume($operationClass, Paper::STATUS_PUBLISHED, $identifiers, $rvId);
                $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = $allPublishedArticles[PapersRepository::TOTAL_ARTICLE];
            }

        } elseif ($operationClass === Search::class) {
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $this->search->getAllFacets($filters[Search::TERMS_PARAM]);
        }

    }

    public function setSerializer(SerializerInterface $serializer): void
    {

        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }

    }
}
