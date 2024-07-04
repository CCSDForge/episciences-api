<?php

namespace App\State;

use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractStateDataProvider
{
    public function __construct(protected EntityManagerInterface $entityManagerInterface, protected Stats $statsService)
    {

    }


}