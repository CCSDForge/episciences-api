<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\JournalMapper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provides the public Journal resource for a single journal (GET /api/journals/{code}).
 *
 * Aligned with the collection provider (GET /api/journals/): only the public fields of
 * the journal are exposed, through the shared JournalMapper. The item deliberately omits
 * the `number` field, which is a duplicate of `rvid` on a single journal (the collection
 * keeps it as its 1-based sequential counter). Only an enabled journal is returned; an
 * unknown or disabled journal yields 404 (the provider returns null).
 */
final class JournalItemProvider extends AuthenticationStateProvider implements ProviderInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        Pagination $pagination,
        Security $security,
        private readonly ReviewRepository $reviewRepository,
        private readonly JournalMapper $journalMapper
    ) {
        parent::__construct($entityManager, $logger, $pagination, $security);
    }

    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation->getClass() !== Review::class) {
            return null;
        }

        $journal = $this->reviewRepository->getJournalByIdentifier($uriVariables['code'] ?? null);

        if ($journal === null) {
            return null;
        }

        return $this->journalMapper->toJournal($journal);
    }
}
