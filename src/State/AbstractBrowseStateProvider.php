<?php

namespace App\State;

use ApiPlatform\State\Pagination\Pagination;
use App\Service\Solr\SolrAuthorService;
use App\Service\Solr\SolrFacetService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AbstractBrowseStateProvider extends AbstractStateDataProvider
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected LoggerInterface $logger,
        protected Pagination $pagination,
        protected SolrFacetService $facetService,
        protected SolrAuthorService $authorService
    ) {
        parent::__construct($entityManager, $logger, $pagination);
    }
}
