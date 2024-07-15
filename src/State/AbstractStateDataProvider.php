<?php

namespace App\State;

use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractStateDataProvider
{
    public function __construct(protected EntityManagerInterface $entityManagerInterface, protected Stats $statsService, protected LoggerInterface $logger)
    {

    }


}