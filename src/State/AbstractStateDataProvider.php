<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\AppConstants;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\Volume;
use App\Exception\ResourceNotFoundException;
use App\Repository\ReviewRepository;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractStateDataProvider
{
    use QueryTrait;
    use ToolsTrait;

    public const CONTEXT_JOURNAL_KEY = 'journal';

    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, protected Pagination $pagination)
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
        /** @var Operation $operation */
        $operation = $context['operation'] ?? null;
        $context[self::CONTEXT_JOURNAL_KEY] = null;

        $context['filters']['page'] = (isset($context['filters']['page']) && (int)$context['filters']['page']) > 0 ? (int)$context['filters']['page'] : 1;
        $context['filters']['pagination'] = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);

        if (isset($context['filters']['code'])) {
            $code = $context['filters']['code'];

        } elseif (isset($context['filters']['rvcode'])) {
            $code = $context['filters']['rvcode'];
        } else {
            $code = null;
        }

        if ($code === '{code}') {
            $code = null;
        }

        if ($code) {

            /** @var ReviewRepository $reviewRepo */
            $reviewRepo = $this->entityManager->getRepository(Review::class);
            $journal = $reviewRepo->getJournalByIdentifier($code);
            $context[self::CONTEXT_JOURNAL_KEY] = $journal;

            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

            $context['filters']['rvid'] = $journal->getRvid();
            $context['filters'][ReviewSetting::DISPLAY_EMPTY_VOLUMES] = (bool)$journal->getSetting(ReviewSetting::DISPLAY_EMPTY_VOLUMES);
            // todo : does not yet exist as a Journal's parameter (the default value at the moment is true)
            //$context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE] = (bool)$journal->getSetting(ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE);
            $context[ReviewSetting::ALLOW_BROWSE_ACCEPTED_ARTICLE] = true;
        }

        if (isset($context['filters'][AppConstants::YEAR_PARAM])) {
            $context['filters'][AppConstants::YEAR_PARAM] = $this->processYears($context['filters'][AppConstants::YEAR_PARAM]);
        }

        if ($operation &&
            $operation->getClass() === Volume::class
        ) {

            if (
                isset($context['filters']['type']) &&
                $context['filters']['type']

            ) {
                $qb = $this->entityManager->createQueryBuilder();
                $tFilters = (array)$context['filters']['type'];
                $context['filters']['type'] = (array)$this->processTypes($qb, $tFilters);
            }

            if (isset($context['filters']['vid'])) {
                $context['filters']['vid'] = $this->arrayCleaner((array)$context['filters']['vid']);
            }

        }

    }
}
