<?php

namespace App\State;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Exception\ResourceNotFoundException;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractStateDataProvider
{
    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger)
    {

    }

    /**
     * @throws ResourceNotFoundException
     */
    public function checkSeekPosition(PaginatorInterface $paginator): void
    {
        $currentPage = $paginator->getCurrentPage();
        $firstResult = ($currentPage - 1) * $paginator->getItemsPerPage();

        if ($currentPage < 1 || $currentPage > $paginator->getLastPage()) {
            throw new ResourceNotFoundException(sprintf('Oops! Seek position %s is out of range: page %s is not available', $firstResult, $currentPage));
        }

    }

    public function checkAndProcessFilters(array &$context = []): void
    {

        $context['filters']['page'] = (isset($context['filters']['page']) && (int)$context['filters']['page']) > 0 ? (int)$context['filters']['page'] : 1;
        $context['filters']['pagination'] = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);

    }


}