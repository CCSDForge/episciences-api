<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Exception\ResourceNotFoundException;
use App\Resource\Search;
use App\Resource\SolrDoc;
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
        $page = $context['filters']['page'];

        $firstResult = 0;

        $filters = $context['filters'] ?? [];

        $terms = !empty($filters[Search::TERMS_PARAM]) ? trim($filters[Search::TERMS_PARAM]) : null;

        if (empty($terms)) {
            throw new ResourceNotFoundException('Oops! the required field {terms} is empty or not filled in');
        }


        $maxResults = $filters['itemsPerPage'] ?? $operation->getPaginationMaximumItemsPerPage();


        if ($isPaginationEnabled) {
            $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
            $firstResult = ($page - 1) * $maxResults;
        }

        $result = $this->client
            ->setJournal($journal)
            ->setLogger($this->logger)
            ->setSearchPrams($filters)
            ->search($terms);

        $response = $result->getData()['response'] ?? [];
        $docs = $response['docs'] ?? [];
        $oDocs = [];

        foreach ($docs as $values) {
            $oDocs[] = new SolrDoc($values);
        }

        $paginator = new ArrayPaginator($oDocs, $firstResult, $maxResults);
        $this->checkSeekPosition($paginator);
        return $paginator;
    }
}
