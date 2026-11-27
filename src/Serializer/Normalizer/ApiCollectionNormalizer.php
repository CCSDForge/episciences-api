<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Metadata\HttpOperation;
use App\Doctrine\UnpaginatedCollectionLimitExtension;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiCollectionNormalizer extends AbstractNormalizer implements NormalizerInterface, NormalizerAwareInterface, SerializerAwareInterface
{
    use QueryTrait;

    public const string FORMAT = 'jsonld';

    private ?RequestStack $requestStack = null;

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->decorated->setNormalizer($normalizer);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {

        $data = $this->decorated->normalize($object, $format, $context);

        if (!isset($context['resource_class']) || isset($context['api_sub_level'])) { // array nested in a resource, not a collection
            return $data;
        }

        /** @var HttpOperation $operation */
        $operation = $context['operation'] ?? $context['root_operation'] ?? null;

        if (!$operation) {
            return null;
        }

        $this->setTruncatedTotalItems($data);

        $operationClass = $operation->getClass();

        $filters = [];
        $journal = null;

        $requestUri = $context['uri'] ?? '';
        $queryPart = parse_url($requestUri, PHP_URL_QUERY);
        parse_str($queryPart ?? $requestUri, $parsedUri);

        $rvCode = $parsedUri['rvcode'] ?? $parsedUri['code'] ?? null;
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

        if ($operationClass === Paper::class) {
            // The facets of the papers are computed on the requested scope (journal, section, volume),
            // also when no paper matches the other filters
            if (isset($parsedUri['sid'])) {
                $filters['sid'] = $parsedUri['sid'];
            }

            if (!$journal && isset($parsedUri['rvid'])) {
                $filters['rvid'] = $parsedUri['rvid'];
            }
        }

        if (empty($hydraMember)) {
            $this->addHydraContext($data, $operationClass, $journal, [], $filters);
            return $data;
        }

        $identifiers = [];
        $rvId = $journal?->getRvid();

        if (
            $filters !== [] &&
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

    /**
     * Without pagination, a capped collection holds at most the maximum number of items, which the Hydra
     * normalizer counts as its total. The total number of items found by the provider (TOTAL_ITEMS_ATTRIBUTE,
     * also sent in the X-Total-Count header) replaces this count, so that comparing hydra:totalItems with the
     * members tells the truncation. No hydra:view is added: the collection is still not paginated.
     */
    private function setTruncatedTotalItems(mixed &$data): void
    {
        $attributes = $this->requestStack?->getCurrentRequest()?->attributes;
        $totalItems = $attributes?->get(UnpaginatedCollectionLimitExtension::TOTAL_ITEMS_ATTRIBUTE);

        if (!is_array($data) || !is_int($totalItems) || !$attributes->has(UnpaginatedCollectionLimitExtension::TRUNCATED_ATTRIBUTE)) {
            return;
        }

        foreach (['hydra:totalItems', 'totalItems'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $totalItems;
            }
        }
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * Same types as the decorated Hydra collection normalizer: the paginators and the PHP arrays
     * (collections requested without pagination), otherwise serialized as a plain list.
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
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
                $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $this->authorService->setJournal($journal)->getCountArticlesByAuthorsFirstLetter();
            }

        } elseif ($operationClass === Statistic::class) {
            $repo = $this->entityManager->getRepository(Paper::class);
            $years = $repo->getYearRange($rvId, null);
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
            // The lists ignore the requested year[] and type[]: choosing a value must not hide the other ones
            $scope = [
                'rvid' => $rvId ?? $filters['rvid'] ?? null,
                'sid' => $filters['sid'] ?? null,
                'vid' => $filters['vid'] ?? null,
            ];
            $range = [];
            $counts = [];

            if (!$isOnlyAccepted) {
                $range['publicationYears'] = $repo->getRange($scope);
                // the number of papers by year among the requested types (what choosing the year returns)
                $counts['publicationYears'] = $this->countsOf(
                    $range['publicationYears'],
                    $repo->countByPublicationYear($scope, $filters['type'] ?? [])
                );
            }

            $range['types'] = $repo->getTypes($scope + ['isOnlyAccepted' => $isOnlyAccepted]);
            // the number of papers by type in the requested years (ignored by the list of the accepted papers)
            $counts['types'] = $this->countsOf(
                $range['types'],
                $repo->countByType($scope + ['isOnlyAccepted' => $isOnlyAccepted], $isOnlyAccepted ? [] : ($filters['year'] ?? []))
            );

            $range['counts'] = $counts;
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = $range;

        } elseif ($operationClass === News::class) {
            $data[sprintf('hydra:%s', RangeInterface::RANGE)] = ['years' => $repo ? new Range()->setYears($repo->getRange($rvCode))->getYears() : []];
        } elseif ($operationClass === Volume::class || $operationClass === Section::class) {
            $data[sprintf('hydra:%s', PapersRepository::TOTAL_ARTICLE)] = 0;

            if ($operationClass === Volume::class) {

                $isDisplayEmptyVolume = $filters[ReviewSetting::DISPLAY_EMPTY_VOLUMES] ?? false;
                $onlyPublished = !isset($filters['isGranted']) || !$filters['isGranted']; // FALSE IF GRANTED SECRETARY

                $rangeType = new RangeType()
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

    /**
     * The number of papers of each value of a facet, 0 for a value without paper; a JSON object, even when empty.
     *
     * @param array<int|string> $values
     * @param array<int|string, int> $counts
     *
     * @return array<int|string, int>|\ArrayObject<int|string, int>
     */
    private function countsOf(array $values, array $counts): array|\ArrayObject
    {
        $result = [];

        foreach ($values as $value) {
            $result[$value] = $counts[$value] ?? 0;
        }

        return $result === [] ? new \ArrayObject() : $result;
    }

    public function setSerializer(SerializerInterface $serializer): void
    {

        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }

    }
}
