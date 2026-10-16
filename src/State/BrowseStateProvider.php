<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Exception\ResourceNotFoundException;
use App\Resource\Facet;
use App\Resource\SolrDoc;
use App\Service\Solr\SolrConstants;

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
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
            foreach (['code', 'letter', 'search', 'sort'] as $param) {
                if (!isset($context['filters'][$param]) && $request->query->has($param)) {
                    $context['filters'][$param] = $request->query->get($param);
                }
            }
        }

        $this->checkAndProcessFilters($context);
        $journal = $context[self::CONTEXT_JOURNAL_KEY] ?? null;

        $isPaginationEnabled = $context['filters']['pagination'];
        $page =  $context['filters']['page'];

        $firstResult = 0;
        $maxResults = SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS;


        if (isset($uriVariables[self::AUTHOR_FULlNAME])) { // "browse/authors-search" collection

            $fullName = trim($uriVariables[self::AUTHOR_FULlNAME]);
            $result = $this->authorService->setJournal($journal)->getSolrAuthorsByFullName($fullName);
            $docs = $result['response']['docs'] ?? [];

            $response = [];

            foreach ($docs as $values) {
                $response[] = new SolrDoc($values);
            }

            $this->flagIfTruncated($context, (int)($result['response']['numFound'] ?? count($response)), min($maxResults, count($response)));

            if ($isPaginationEnabled) {
                $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
                $firstResult = ($page - 1) * $maxResults;
            }

            return new ArrayPaginator($response, $firstResult, $maxResults);

        }
        $authorFs = 'authorLastNameFirstNamePrefixed_fs';
        // "browse/authors" collection
        $letter = isset($context['filters']['letter']) ? mb_ucfirst($context['filters']['letter']) : 'all';

        $search = isset($context['filters']['search']) ? mb_ucfirst($context['filters']['search']) : '';

        $sortType = $context['filters']['sort'] ?? 'index';

        $facetParams = [
            'facetFieldName' => $authorFs,
            'letter' => $letter,
            'sortType' => $sortType,
            'search' => $search
        ];

        if ($isPaginationEnabled) {
            // Only the requested page is fetched from Solr, with the total number of authors
            $itemsPerPage = max(1, min(
                (int)($context['filters']['itemsPerPage'] ?? $operation->getPaginationItemsPerPage() ?? AppConstants::DEFAULT_ITEM_PER_PAGE),
                $maxResults
            ));

            $facetPage = $this->facetService->setJournal($journal)->getSolrFacetPage($facetParams, ($page - 1) * $itemsPerPage, $itemsPerPage);

            $paginator = new TraversablePaginator(new \ArrayIterator($this->toFacets($authorFs, $facetPage['values'])), $page, $itemsPerPage, $facetPage['total']);
            $this->checkSeekPosition($paginator);

            return $paginator;
        }

        // Without pagination, one more author is requested to tell a truncated result.
        $authors = $this->toFacets($authorFs, $this->facetService->setJournal($journal)->getSolrFacet(['facetLimit' => $maxResults + 1] + $facetParams));

        $this->flagIfTruncated($context, count($authors), $maxResults);
        $authors = array_slice($authors, 0, $maxResults); // without the extra author requested to tell a truncated result

        $paginator = new ArrayPaginator($authors, $firstResult, $maxResults);

        $this->checkSeekPosition($paginator);

        return $paginator;

    }

    /**
     * @param array<string, mixed> $values facet values (name => count)
     * @return Facet[]
     */
    private function toFacets(string $field, array $values): array
    {
        $facets = [];

        foreach ($values as $name => $count) {
            $facets[] = new Facet()
                ->setField($field)
                ->setValues(['name' => $name, 'count' => $count]);
        }

        return $facets;
    }
}
