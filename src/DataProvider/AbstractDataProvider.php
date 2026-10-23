<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\Paper;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use App\Resource\Statistic;
use App\Service\StatisticPapers;
use App\Service\Stats;
use App\Traits\CheckExistingResourceTrait;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractDataProvider
{
    use CheckExistingResourceTrait;
    use ToolsTrait;

    public function __construct(private readonly EntityManagerInterface $entityManagerInterface, private readonly Stats $statsService, private readonly StatisticPapers $statisticPapers)
    {

    }


    abstract protected function supports(?Operation $operation = null): bool;

    protected function getCollection(Operation $operation, array $context = []): array|AbstractStatResource|null
    {

        $result = null;
        $cFilters = $context['filters'] ?? [];

        if (isset($context['uri_variables'])) { // exp. {code} = rvcode

            $filters['is'] = $context['uri_variables']; // available filters

            $journal = $this->statsService->getJournal($context['uri_variables']);

            if (!$journal instanceof \App\Entity\Review) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $context['uri_variables']['code']));
            }


            $filters['is']['rvid'] = (string)$journal->getRvid();

            $this->addFilters($filters['is'], $operation->getName(), $cFilters);

            $isDashboard = $operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][0];
            $withPapers = StatisticPapers::isRequested($cFilters[Statistic::WITH_PAPERS_PARAM] ?? null);

            if ($withPapers && !$isDashboard) {
                throw new ResourceNotFoundException(sprintf('Oops! %s is only available on the dashboard', Statistic::WITH_PAPERS_PARAM));
            }

            // before any computation: reserved for the administrators of the journal
            $conflictedPapers = $withPapers ? $this->statisticPapers->getConflictedPapers($journal) : null;

            if ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][1]) {
                $result = $this->statsService->getSubmissionsStat($filters);

            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][2]) {
                $result = $this->statsService->getDelayBetweenSubmissionAndLatestStatus($filters);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][3]) {
                $result = $this->statsService->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_PUBLISHED);
            } elseif ($isDashboard) {
                $result = $this->statsService->getDashboard($context, $filters);

                if ($conflictedPapers !== null && is_array($result->getValue())) {
                    [$papers, $hiddenPapers] = $this->statisticPapers->toDocIds($this->statsService->getDashboardPaperIds($filters), $conflictedPapers);
                    $result->setValue(array_merge($result->getValue(), ['papers' => $papers, 'hiddenPapers' => $hiddenPapers]));
                }
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][4]) {
                $result = $this->statsService->getUserStats($filters['is']);
            }
        }

        if ($result instanceof AbstractStatResource) {
            $result->setRequestedFilters($cFilters);
        }

        return $result;

    }


    /**
     * @throws ResourceNotFoundException an invalid startAfterDate, as for /api/statistics/*
     */
    private function addFilters(array &$availableFilters, string $operationName, array &$contextFilters = []): void
    {
        $startDate = $contextFilters[AppConstants::START_AFTER_DATE] ?? '';

        if ($startDate !== '') {
            $startDate = is_string($startDate) ? urldecode($startDate) : null;

            if ($startDate === null || !self::isValidDate($startDate)) {
                throw new ResourceNotFoundException(sprintf('Oops! invalid %s: the expected format is YYYY-MM-DD', AppConstants::START_AFTER_DATE));
            }

            $availableFilters[AppConstants::START_AFTER_DATE] = $startDate;
        }

        if (isset($contextFilters[AppConstants::WITH_DETAILS])) {
            $availableFilters[AppConstants::WITH_DETAILS] = true;
        }

        if (isset($contextFilters[AppConstants::YEAR_PARAM])) {
            $availableFilters['submissionDate'] = $contextFilters[AppConstants::YEAR_PARAM];
        }

        // by operation name: enable additional filters
        if ($operationName === AppConstants::STATS_NB_SUBMISSIONS_ITEM) {

            foreach ($contextFilters as $filter => $value) {

                if (
                    !isset($availableFilters[$filter]) &&
                    $filter !== AppConstants::START_AFTER_DATE && // already checked above
                    in_array($filter, Stats::AVAILABLE_PAPERS_FILTERS, true)
                ) {
                    $availableFilters[$filter] = $value;
                }

            }

        }
    }

}

