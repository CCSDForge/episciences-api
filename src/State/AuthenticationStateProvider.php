<?php

namespace App\State;

use ApiPlatform\State\Pagination\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AuthenticationStateProvider extends AbstractStateDataProvider
{
   public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, protected Pagination $pagination, protected readonly Security $security){
       parent::__construct($entityManager, $logger, $pagination);
   }
}
