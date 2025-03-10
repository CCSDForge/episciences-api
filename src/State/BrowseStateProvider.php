<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Exception\ResourceNotFoundException;
use App\Resource\Facet;
use App\Resource\SolrDoc;
use App\Service\Solr;

class BrowseStateProvider extends AbstractBrowseStateProvider implements ProviderInterface
{
    public const AUTHOR_FULlNAME = 'author_fullname';

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|object[]|null
     * @throws ResourceNotFoundException
     */

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->checkAndProcessFilters($context);
        $journal = $context[self::CONTEXT_JOURNAL_KEY] ?? null;

        $isPaginationEnabled = $context['filters']['pagination'];
        $page =  $context['filters']['page'];

        $firstResult = 0;
        $maxResults = Solr::SOLR_MAX_RETURNED_FACETS_RESULTS;


        if (isset($uriVariables[self::AUTHOR_FULlNAME])) { // "browse/authors-search" collection

            $fullName = trim($uriVariables[self::AUTHOR_FULlNAME]);
            $result = $this->solrSrv->setJournal($journal)->getSolrAuthorsByFullName($fullName);
            $docs = $result['response']['docs'] ?? [];

            $response = [];

            foreach ($docs as $values) {
                $response[] = new SolrDoc($values);
            }

            if ($isPaginationEnabled) {
                $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
                $firstResult = ($page - 1) * $maxResults;
            }

            return new ArrayPaginator($response, $firstResult, $maxResults);

        }
        $authorFs = 'authorLastNameFirstNamePrefixed_fs';
        // "browse/authors" collection
        $authors = [];
        $letter = isset($context['filters']['letter']) ? mb_ucfirst($context['filters']['letter']) : 'all';

        $search = isset($context['filters']['search']) ? mb_ucfirst($context['filters']['search']) : '';

        $sortType = $context['filters']['sort'] ?? 'index';


        $result = $this->solrSrv->setJournal($journal)->getSolrFacet([
            'facetFieldName' => $authorFs,
            'facetLimit' => $maxResults,
            'letter' => $letter,
            'sortType' => $sortType,
            'search' => $search
        ]);


        foreach ($result as $name => $count) {
            $author = (new Facet())
                ->setField($authorFs)
                ->setValues(['name' => $name, 'count' => $count]);
            $authors[] = $author;
        }

        if ($isPaginationEnabled) {
            $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
            $firstResult = ($page - 1) * $maxResults;
        }

        $paginator = new ArrayPaginator($authors, $firstResult, $maxResults);

        $this->checkSeekPosition($paginator);

        return $paginator;

    }
}
