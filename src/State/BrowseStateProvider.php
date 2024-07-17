<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Resource\Facet;
use App\Resource\SolrDoc;
use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BrowseStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    public const AUTHOR_FULlNAME = 'author_fullname';

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, protected Solr $solrSrv,)
    {
        parent::__construct($entityManager, $logger);

    }

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|object[]|null
     * @throws ResourceNotFoundException
     */

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $journal = null;
        $code = $context['filters']['code'] ?? null;

        if ($code === '{code}') {
            $code = null;
        }

        if ($code) {

            $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);

            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

        }

        $this->checkAndProcessFilters($context);

        $isPaginationEnabled = $context['filters']['pagination'];
        $page =  $context['filters']['page'];

        $firstResult = 0;
        $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: Solr::SOLR_MAX_RETURNED_FACETS_RESULTS;


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
        // "browse/authors" collection
        $authors = [];
        $letter = isset($context['filters']['letter']) ? mb_ucfirst($context['filters']['letter']) : 'all';
        $search = isset($context['filters']['search']) ? mb_ucfirst($context['filters']['search']) : '';

        $sortType = $context['filters']['sort'] ?? 'index';

        $result = $this->solrSrv->setJournal($journal)->getSolrFacet([
            'facetFieldName' => 'author_fullname_fs',
            'facetLimit' => $maxResults,
            'letter' => $letter,
            'sortType' => $sortType,
            'search' => $search
        ]);


        foreach ($result as $name => $count) {
            $author = (new Facet())
                ->setField('author_fullname_fs')
                ->setValues(['name' => $name, 'count' => $count]);
            $authors[] = $author;
        }

        if ($isPaginationEnabled) {
            $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
            $firstResult = ($page - 1) * $maxResults;
        }

        $paginator = new ArrayPaginator($authors, $firstResult, $maxResults);

        $this->checkSeekPosition($paginator, $maxResults );

        return $paginator;

    }
}
