<?php

namespace App\State;

use App\Service\Solr;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AbstractBrowseStateProvider extends AbstractStateDataProvider
{
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, protected Solr $solrSrv,)
    {
        parent::__construct($entityManager, $logger);

    }

}