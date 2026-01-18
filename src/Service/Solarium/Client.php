<?php

namespace App\Service\Solarium;

use App\Entity\Review;
use App\Resource\Search;
use App\Service\Solr\SolrConstants;
use Psr\Log\LoggerInterface;
use Solarium\Component\QueryInterface;
use Solarium\Core\Query\AbstractQuery;
use Solarium\Core\Query\Helper;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

class Client extends \Solarium\Client
{
    private ?Review $journal;
    private ?LoggerInterface $logger;
    private array $searchPrams = [];
    private QueryInterface|Query|AbstractQuery $query;
    private array $excludedFilterTags = [];
    public const TAG_SEPARATOR = '__';


    public function getAllFacets(string $q = Search::DEFAULT_TERMS): array
    {
        $allFacetsArray = [];
        $facets = $this->getSolrConfig('solr.es.facets');
        $this->buildSearchQuery($q)->addFacets();
        $result = $this->select($this->getQuery());

        foreach ($facets as $facet) {
            $facetName = '';
            $hasSepInValue = $facet['hasSepInValue'] ?? false;
            $facetValues = $result->getFacetSet()?->getFacet($facet['fieldName'])?->getValues();

            if ($facet['fieldName'] === 'author_fullname_s') {
                $facetName = Search::AUTHOR_FACET_NAME;

            } elseif ($facet['fieldName'] === 'section_title_fs') {
                $facetName = Search::SECTION_FACET_NAME;

            } elseif ($facet['fieldName'] === 'volume_title_fs') {
                $facetName = Search::VOLUME_FACET_NAME;

            } elseif ($facet['fieldName'] === 'publication_date_year_fs') {
                $facetName = Search::PUBLICATION_DATE_YEAR_FILTER;

            } elseif ($facet['fieldName'] === 'doc_type_fs') {
                $facetName = Search::DOC_TYPE_FILTER;

            }
            if ($facetName !== '') {

                foreach ($facetValues as $label => $count) {

                    if ($hasSepInValue) {
                        [$identifier, $prefixedLabel] = explode(SolrConstants::SOLR_FACET_SEPARATOR, $label);
                        [$lang, $newLabel] = explode('_', $prefixedLabel);

                        $allFacetsArray[$facetName][$lang][$identifier][$newLabel] = $count;

                    } else {
                        $allFacetsArray[$facetName][$label] = $count;
                    }
                }

            }

        }

        return $allFacetsArray;

    }

    public function getSolrConfig(string $key, string $format = null): array
    {
        $toArray = [];
        $path = null;

        $isDefaultPath = false;
        $defaultPath = sprintf('%s/../config/solr/%s/%s.%s', \dirname(__DIR__), 'default', $key, !$format ? 'json' : $format);

        if (is_file($defaultPath) && is_readable($defaultPath)) {
            $isDefaultPath = true;
            $path = $defaultPath;
        }

        if ($rvCode = $this->getJournal()?->getCode()) {
            $path = sprintf('%s/../config/solr/%s/%s.%s', \dirname(__DIR__), $rvCode, $key, !$format ? 'json' : $format);
            if ($isDefaultPath && (!is_file($path) || !is_readable($path))) {
                $path = $defaultPath;
            }
        }

        if ($path) {

            $config = file_get_contents($path);

            if ($config) {
                try {
                    $toArray = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {

                    if ($this->getLogger()) {
                        $this->logger->critical($e->getMessage());
                    }

                }
            }

        }

        return $toArray;
    }

    public function getJournal(): ?Review
    {
        return $this->journal;
    }

    public function setJournal(Review $journal = null): self
    {
        $this->journal = $journal;
        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger = null): self
    {
        $this->logger = $logger;
        return $this;
    }


    public function buildSearchQuery(string $q = Search::DEFAULT_TERMS, int $start = 0, $rows = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS): self
    {

        $query = $this
            ->createSelect()
            ->setOmitHeader(true)
            ->setResponseWriter(AbstractQuery::WT_PHPS)
            ->setQuery($q)
            ->setStart($start)
            ->setFields($this->getSolrConfig('solr.es.returnedFields'));

        $query->setRows($rows);

        if ($this->journal) {
            $query
                ->createFilterQuery(sprintf('df%s', $this->journal->getRvid()))
                ->setQuery(sprintf('revue_id_i:%s', $this->journal->getRvid()));
        }

        $query->getDisMax()->setQueryParser('edismax');
        $this->setQuery($query);

        return $this;
    }

    public function search(string $q = Search::DEFAULT_TERMS, int $start = 0): Result|ResultInterface
    {

        $query = $this->buildSearchQuery($q, $start)->addFilters()->getQuery();

        try {
            $result = $this->select($query);
        } catch (HttpException $e) {
            $this->logger->critical($e->getMessage());
            throw new HttpException("/!\ Couldn't connect to server: we suggest you try again in a few moments. If doesn't work after a few minutes please contact the support.");
        }

        return $result;

    }

    /**
     * @return array
     */
    public function getSearchPrams(): array
    {
        return $this->searchPrams;
    }

    /**
     * @param array $searchPrams
     * @return Client
     */
    public function setSearchPrams(array $searchPrams): self
    {

        foreach ($searchPrams as $key => $value) {

            if (!array_key_exists($key, Search::SEARCH_FILTERS_MAPPING)) {
                continue;
            }

            $this->searchPrams[Search::SEARCH_FILTERS_MAPPING[$key]] = $value;

        }

        return $this;
    }


    public function addFilters(): self
    {
        $filters = $this->getSearchPrams();
        $query = $this->getQuery();
        $index = 0;

        $excludedFilterTags = [];

        $helper = new Helper($query);

        foreach ($filters as $param => $value) {

            $value = (array)$value;
            $compiledValues = '';

            foreach ($value as $key => $current) {
                if (
                    $param === Search::SEARCH_FILTERS_MAPPING[Search::AUTHOR_FULL_NAME_FILTER] ||
                    $param === Search::SEARCH_FILTERS_MAPPING[Search::DOC_TYPE_FILTER]

                ) {
                    $current = trim($current);
                    $current = trim($current, '"');
                    $current = $helper->escapeTerm($current);

                } elseif (
                    $param === Search::SEARCH_FILTERS_MAPPING[Search::VOLUME_FILTER] ||
                    $param === Search::SEARCH_FILTERS_MAPPING[Search::SECTION_FILTER] ||
                    $param === Search::SEARCH_FILTERS_MAPPING[Search::PUBLICATION_DATE_YEAR_FILTER]
                ) {
                    $current = (int)$current;
                }

                $value[$key] = $current;

            }

            foreach ($value as $val) {
                $compiledValues .= $val;
                $compiledValues .= ' ';
            }

            $compiledValues = trim($compiledValues);

            if (empty($compiledValues)) {
                continue;
            }

            $tag = sprintf('tag%s%s%s', $index, self::TAG_SEPARATOR, $param);
            $excludedFilterTags[] = $tag;

            $query->createFilterQuery($tag)->setQuery(sprintf('%s:(%s)', $param, $compiledValues))->addTag($tag);

            $index++;
        }

        $this->setExcludedFilterTags($excludedFilterTags);

        return $this;

    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function setQuery(Query $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function addFacets(): self
    {

        $facets = $this->getSolrConfig('solr.es.facets');

        if (empty($facets)) {
            return $this;
        }

        $excludedTags = $this->getExcludedFilterTags();

        $this->query->addParam('facet.threads', count($facets));
        $facetSet = $this->getQuery()->getFacetSet();

        foreach ($facets as $facet) {
            $current = $facetSet
                ->createFacetField($facet ['fieldName'])
                ->setField($facet ['fieldName'])
                ->setLimit($facet['maxReturned'])
                ->setMincount($facet ['minCount']);


            if ($facet['sort'] === SolrConstants::SOLR_INDEX || $facet['sort'] === SolrConstants::SOLR_FACET_COUNT) {
                $current->setSort($facet['sort']);
            }

            foreach ($excludedTags as $tag) {
                [$tag, $fieldName] = explode(self::TAG_SEPARATOR, $tag);

                if ($fieldName === $facet['fieldName']) {
                    $current->addExclude($tag);
                }
            }
        }

        return $this;

    }


    /**
     * @return array
     */
    public function getExcludedFilterTags(): array
    {
        return $this->excludedFilterTags;
    }

    /**
     * @param array $excludedFilterTags
     * @return Client
     */
    public function setExcludedFilterTags(array $excludedFilterTags): self
    {
        $this->excludedFilterTags = $excludedFilterTags;
        return $this;
    }


}