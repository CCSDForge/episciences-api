<?php

namespace App\State;

use ApiPlatform\State\Pagination\Pagination;
use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AbstractBrowseStateProvider extends AbstractStateDataProvider
{
    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, protected Pagination $pagination, protected Solr $solrSrv)
    {
        parent::__construct($entityManager, $logger, $pagination);

    }

}
