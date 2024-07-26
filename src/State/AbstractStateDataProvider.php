<?php

namespace App\State;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractStateDataProvider
{
    public const CONTEXT_JOURNAL_KEY = 'journal';
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

    /**
     * @throws ResourceNotFoundException
     */
    public function checkAndProcessFilters(array &$context = []): void
    {
        $context[self::CONTEXT_JOURNAL_KEY] = null;

        $context['filters']['page'] = (isset($context['filters']['page']) && (int)$context['filters']['page']) > 0 ? (int)$context['filters']['page'] : 1;
        $context['filters']['pagination'] = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);

        $code = $context['filters']['code'] ?? null;


        if ($code === '{code}') {
            $code = null;
        }

        if ($code) {

            $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);
            $context[self::CONTEXT_JOURNAL_KEY] = $journal;

            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

        }


    }


}