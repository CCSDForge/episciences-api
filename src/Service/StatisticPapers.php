<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Paper;
use App\Entity\PaperConflicts;
use App\Entity\Review;
use App\Entity\ReviewSetting;
use App\Entity\User;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Resource\Statistic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Visibility of the journal dashboard, as on the statistics page of Episciences (setting "displayStatistics").
 *
 * "withPapers" of the statistics (/api/statistics/ and the journal dashboard): the papers counted by each figure,
 * for the administrators and the editorial secretaries of the journal. The papers for which such a user has declared
 * a conflict of interest are not listed (only counted); the platform administrators are not subject to the conflicts,
 * as in PapersVoter.
 */
final readonly class StatisticPapers
{
    public function __construct(private EntityManagerInterface $entityManager, private Security $security)
    {
    }

    /**
     * @throws ResourceNotFoundException not a boolean
     */
    public static function isRequested(mixed $withPapers): bool
    {
        if ($withPapers === null || $withPapers === '') {
            return false;
        }

        $value = is_scalar($withPapers) ? filter_var($withPapers, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;

        if ($value === null) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid %s: a boolean is expected', Statistic::WITH_PAPERS_PARAM));
        }

        return $value;
    }

    /**
     * The dashboard follows the setting "displayStatistics" of the journal: hidden by default (to everyone),
     * public, or reserved for the editorial secretaries of the journal (and the roles above).
     *
     * @throws AccessDeniedException
     */
    public function denyAccessUnlessDashboardIsVisible(Review $journal): void
    {
        $visibility = $journal->getSetting(ReviewSetting::DISPLAY_STATISTICS);

        // same test as Episciences: an empty value hides the statistics, any other value than "2" displays them
        if (in_array($visibility, [null, '', '0'], true)) {
            throw new AccessDeniedException('The statistics of this journal are not available');
        }

        if ($visibility !== ReviewSetting::DISPLAY_STATISTICS_SECRETARIES || $this->security->isGranted('ROLE_EPIADMIN')) {
            return;
        }

        $user = $this->security->getUser();

        // the journal of the JWT (roles of this journal) must be the requested one
        if (!$user instanceof User || !$this->security->isGranted('ROLE_SECRETARY') || $user->getCurrentJournalID() !== $journal->getRvid()) {
            throw new AccessDeniedException('The statistics of this journal are reserved for its editorial secretaries');
        }
    }

    /**
     * @return array<int, true> the papers (PAPERID) not to list because of a declared conflict of interest
     * @throws AccessDeniedException not an administrator or an editorial secretary of the journal
     */
    public function getConflictedPapers(Review $journal): array
    {
        if ($this->security->isGranted('ROLE_EPIADMIN')) {
            return [];
        }

        $user = $this->security->getUser();

        // the journal of the JWT (roles of this journal) must be the requested one;
        // ROLE_ADMINISTRATOR inherits ROLE_SECRETARY (see role_hierarchy), so ROLE_SECRETARY covers both
        if (!$user instanceof User || !$this->security->isGranted('ROLE_SECRETARY') || $user->getCurrentJournalID() !== $journal->getRvid()) {
            throw new AccessDeniedException(sprintf('The lists of papers (%s) are reserved for the administrators and the editorial secretaries of the journal', Statistic::WITH_PAPERS_PARAM));
        }

        if (!$journal->getSetting('isCoiEnabled')) {
            return [];
        }

        $conflicted = [];

        foreach ($this->entityManager->getRepository(PaperConflicts::class)->findBy(['by' => $user->getUid(), 'answer' => PaperConflicts::AVAILABLE_ANSWER['yes']]) as $conflict) {
            $conflicted[$conflict->getPaperId()] = true;
        }

        return $conflicted;
    }

    /**
     * The papers to list: one DOCID by counted paper (its latest version), without the conflicted papers.
     *
     * @param array<string, list<int>> $paperIdsByFigure PAPERIDs counted by each figure
     * @param array<int, true> $conflictedPapers see getConflictedPapers()
     * @return array{0: array<string, list<int>>, 1: array<string, int>} DOCIDs by figure, number of papers not listed by figure
     */
    public function toDocIds(array $paperIdsByFigure, array $conflictedPapers): array
    {
        $visibleByFigure = [];
        $hiddenByFigure = [];

        foreach ($paperIdsByFigure as $figure => $paperIds) {
            $visibleByFigure[$figure] = array_values(array_filter($paperIds, static fn(int $paperId): bool => !isset($conflictedPapers[$paperId])));
            $hiddenByFigure[$figure] = count($paperIds) - count($visibleByFigure[$figure]);
        }

        /** @var PapersRepository $papersRepository */
        $papersRepository = $this->entityManager->getRepository(Paper::class);
        $docIds = $papersRepository->getLatestDocIds(array_merge(...array_values($visibleByFigure)));

        foreach ($visibleByFigure as $figure => $paperIds) {
            $figureDocIds = array_values(array_map(static fn(int $paperId): int => $docIds[$paperId] ?? $paperId, $paperIds));
            sort($figureDocIds);
            $visibleByFigure[$figure] = $figureDocIds;
        }

        return [$visibleByFigure, $hiddenByFigure];
    }
}
