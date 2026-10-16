<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Exception\ResourceNotFoundException;
use App\Resource\Search;
use App\Resource\SolrDoc;
use App\Service\Solr\SolrConstants;
use App\Service\Solarium\Client;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SearchStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    public function __construct(private readonly Client $client, protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, Pagination $pagination)
    {
        parent::__construct($entityManager, $logger, $pagination);

    }

    /**
     * @throws ResourceNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $this->checkAndProcessFilters($context);


        $journal = $context[self::CONTEXT_JOURNAL_KEY] ?? null;

        $isPaginationEnabled = $context['filters']['pagination'];
        $page = $isPaginationEnabled ? $context['filters']['page'] : 1;

        $filters = $context['filters'] ?? [];

        $terms = empty($filters[Search::TERMS_PARAM]) ? null : trim((string) $filters[Search::TERMS_PARAM]);

        if (in_array($terms, [null, '', '0'], true)) {
            throw new ResourceNotFoundException('Oops! the required field {terms} is empty or not filled in');
        }


        // Only the requested page is fetched from Solr
        $maxResults = max(1, min(
            (int)($filters['itemsPerPage'] ?? $operation->getPaginationMaximumItemsPerPage() ?? SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS),
            SolrConstants::SOLR_MAX_RETURNED_FACETS_RESULTS
        ));
        $firstResult = ($page - 1) * $maxResults;

        $result = $this->client
            ->setJournal($journal)
            ->setLogger($this->logger)
            ->setSearchPrams($filters)
            ->search($terms, $firstResult, $maxResults);

        $response = $result->getData()['response'] ?? [];
        $docs = $response['docs'] ?? [];
        $oDocs = [];

        foreach ($docs as $values) {
            $oDocs[] = new SolrDoc($values);
        }

        $totalItems = (int)($response['numFound'] ?? count($oDocs));

        if (!$isPaginationEnabled) {
            $this->flagIfTruncated($context, $totalItems, count($oDocs));
            $totalItems = count($oDocs); // like the other collections requested without pagination
        }

        $paginator = new TraversablePaginator(new \ArrayIterator($oDocs), $page, $maxResults, $totalItems);
        $this->checkSeekPosition($paginator);
        return $paginator;
    }
}
