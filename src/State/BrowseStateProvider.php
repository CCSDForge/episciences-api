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

class BrowseStateProvider implements ProviderInterface
{
    public const AUTHOR_FULlNAME = 'author_fullname';

    private EntityManagerInterface $entityManager;
    private Solr $solrSrv;

    public function __construct(EntityManagerInterface $entityManager, Solr $solrSrv,)
    {
        $this->entityManager = $entityManager;
        $this->solrSrv = $solrSrv;

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

            $journal = $this->entityManager->getRepository(Review::class)->findOneBy(['code' => $code]);

            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

        }

        $isPaginationEnabled = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);
        $page = $context['filters']['page'] ?? 1;
        $firstResult = 0;

        $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: Solr::SOLR_MAX_RETURNED_FACETS_RESULTS;


        if (isset($uriVariables[self::AUTHOR_FULlNAME])) { // "browse/authors-search" collection

            $fullName = trim($uriVariables[self::AUTHOR_FULlNAME]);
            $result = $this->solrSrv->setJournal($journal)->getSolrAuthorsByFullName($fullName);
            $docs = $result['response']['docs'] ?? [];

            $response = [];

            foreach ($docs as $values){
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

        return new ArrayPaginator($authors, $firstResult, $maxResults);

    }
}
