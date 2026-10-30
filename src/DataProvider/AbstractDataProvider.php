<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\Paper;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Exception\InvalidQueryParameter;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use App\Resource\Statistic;
use App\Service\StatisticPapers;
use App\Service\Stats;
use App\Traits\CheckExistingResourceTrait;
use App\Traits\QueryTrait;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractDataProvider
{
    use CheckExistingResourceTrait;
    use QueryTrait;
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
                throw InvalidQueryParameter::exception(Statistic::WITH_PAPERS_PARAM, 'Only available on the dashboard.', $cFilters[Statistic::WITH_PAPERS_PARAM]);
            }

            // before any computation
            if ($isDashboard) {
                $this->statisticPapers->denyAccessUnlessDashboardIsVisible($journal);
            }

            // reserved for the administrators of the journal
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
     * @throws ValidationException an invalid startAfterDate or year (422), as for /api/statistics/*
     */
    private function addFilters(array &$availableFilters, string $operationName, array &$contextFilters = []): void
    {
        $startDate = $contextFilters[AppConstants::START_AFTER_DATE] ?? '';

        if ($startDate !== '') {
            $startDate = is_string($startDate) ? urldecode($startDate) : null;

            if ($startDate === null || !self::isValidDate($startDate)) {
                throw InvalidQueryParameter::exception(AppConstants::START_AFTER_DATE, 'The expected format is YYYY-MM-DD.', $contextFilters[AppConstants::START_AFTER_DATE]);
            }

            $availableFilters[AppConstants::START_AFTER_DATE] = $startDate;
        }

        if (isset($contextFilters[AppConstants::WITH_DETAILS])) {
            $availableFilters[AppConstants::WITH_DETAILS] = true;
        }

        $years = $contextFilters[AppConstants::YEAR_PARAM] ?? '';

        if ($years !== '' && $years !== []) {
            $years = is_array($years) || is_string($years) || is_int($years) ? self::toValidYears($years) : null;

            if ($years === null) {
                throw InvalidQueryParameter::exception(AppConstants::YEAR_PARAM, 'The expected format is YYYY.', $contextFilters[AppConstants::YEAR_PARAM]);
            }

            $availableFilters['submissionDate'] = $years;
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

