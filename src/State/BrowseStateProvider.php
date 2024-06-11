<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Review;
use App\Resource\Facet;
use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class BrowseStateProvider implements ProviderInterface
{

    private EntityManagerInterface $entityManager;
    private Solr $solrSrv;

    public function __construct(EntityManagerInterface $entityManager, Solr $solrSrv,)
    {
        $this->entityManager = $entityManager;
        $this->solrSrv = $solrSrv;

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $authors = [];
        $journal = null;

        $isPaginationEnabled = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);
        $page = $filter['page'] ?? 1;
        $firstResult = 0;
        $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: Solr::SOLR_MAX_RETURNED_FACETS_RESULTS;

        $letter = $context['filters']['letter'] ?? 'all';
        $availableLetters = array_merge(range('A', 'Z'), ['all', 'other']);

        if (!in_array($letter, $availableLetters, true)) {
            $letter = 'all';
        }

        $sortType = $context['filters']['sort'] ?? 'index';
        $code = $context['filters']['code'] ?? null;


        if ($code === '{code}') {
            $code = null;
        }

        if ($code) {

            $journal = $this->entityManager->getRepository(Review::class)->findOneBy(['code' => $code]);

            if (!$journal) {
                throw new RuntimeException(sprintf('Oops! not found Journal %s', $code));
            }

        }

        $result = $this->solrSrv->setJournal($journal)->getSolrFacet([
            'facetFieldName' => 'author_fullname_fs',
            'facetLimit' => $maxResults,
            'letter' => $letter,
            'sortType' => $sortType
        ]);


        foreach ($result as $name => $count) {
            $author = (new Facet())
                ->setField('author_fullname_fs')
                ->setValues(['name' => $name, 'count' => $count]);
            $authors[] = $author;
        }

        if ($isPaginationEnabled) {
            $maxResults = $operation->getPaginationItemsPerPage();
            $firstResult = ($page - 1) * $maxResults;
        }

        return new ArrayPaginator($authors, $firstResult, $maxResults);
    }
}
