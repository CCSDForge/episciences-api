<?php

namespace App\Service\Solr;

use ApiPlatform\Metadata\Exception\RuntimeException;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SolrFacetService extends AbstractSolrService
{
    public function getSolrFacet(array $params = []): array
    {
        $query = $this->buildFacetQuery($params);

        if ($query === null) {
            return [];
        }

        try {
            $response = $this->client->request('GET', $query);
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            return $this->parseFacetResults($this->transformFacetFieldsToAssoc($data['facet_counts']['facet_fields']['list'] ?? []));
        } catch (JsonException|TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->critical($e->getMessage());
            throw new RuntimeException('Oops! An error occurred');
        }
    }

    public function getLettersRange(): array
    {
        return [...range('A', 'Z'), SolrConstants::SOLR_OTHERS_PREFIX, SolrConstants::SOLR_ALL_PREFIX];
    }

    private function buildFacetQuery(array $params): ?string
    {
        $facetFieldName = $params['facetFieldName'] ?? '';

        if ($facetFieldName === '') {
            return null;
        }

        $facetLimit = $params['facetLimit'] ?? SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;
        $minCount = $params['minCount'] ?? 1;
        $sortType = ($params['sortType'] ?? SolrConstants::SOLR_INDEX) === SolrConstants::SOLR_FACET_COUNT
            ? SolrConstants::SOLR_FACET_COUNT
            : SolrConstants::SOLR_INDEX;

        $queryParams = [
            'q' => '*:*',
            'rows' => 0,
            'wt' => 'json',
            'indent' => 'false',
            'facet' => 'true',
            'omitHeader' => 'true',
            'facet.limit' => $facetLimit,
            'facet.mincount' => $minCount,
            'facet.field' => "{!key=list}$facetFieldName",
            'facet.sort' => $sortType,
        ];

        $filters = $this->getJournalFilter();
        $this->applyLetterFilter($queryParams, $params['letter'] ?? null);
        $this->applySearchFilter($queryParams, $params['search'] ?? '');

        return $this->buildSolrUrl(self::SOLR_SELECT_ENDPOINT, $queryParams, $filters);
    }

    private function applyLetterFilter(array &$params, ?string $letter): void
    {
        if ($letter === null) {
            return;
        }

        $normalizedLetter = $this->normalizeLetter($letter);

        if ($normalizedLetter !== SolrConstants::SOLR_ALL_PREFIX) {
            $params['facet.prefix'] = $normalizedLetter;
        }
    }

    private function applySearchFilter(array &$params, string $search): void
    {
        if ($search !== '') {
            $params['facet.contains'] = $search;
        }
    }

    private function normalizeLetter(string $letter): string
    {
        $letter = mb_ucfirst($letter);

        if (!in_array($letter, $this->getLettersRange(), true)) {
            return SolrConstants::SOLR_ALL_PREFIX;
        }

        if ($letter === SolrConstants::SOLR_OTHERS_PREFIX) {
            return SolrConstants::SOLR_OTHERS_FACET_SEPARATOR;
        }

        return $letter;
    }

    private function transformFacetFieldsToAssoc(array $flatArray): array
    {
        $result = [];
        for ($i = 0, $count = count($flatArray); $i < $count; $i += 2) {
            if (isset($flatArray[$i + 1])) {
                $result[$flatArray[$i]] = $flatArray[$i + 1];
            }
        }
        return $result;
    }

    private function parseFacetResults(array $list): array
    {
        $result = [];

        foreach ($list as $name => $count) {
            $cleanName = str_replace(SolrConstants::SOLR_OTHERS_FACET_SEPARATOR, '', $name);
            $parts = explode(SolrConstants::SOLR_FACET_SEPARATOR, $cleanName);

            if (count($parts) > 1) {
                $result[$parts[0]] = [
                    SolrConstants::SOLR_FACET_NAME => $parts[1],
                    SolrConstants::SOLR_FACET_COUNT => $count,
                ];
            } else {
                $result[$cleanName] = $count;
            }
        }

        return $result;
    }
}
