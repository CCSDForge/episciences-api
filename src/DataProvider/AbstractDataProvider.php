<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\Paper;
use App\Exception\ResourceNotFoundException;
use App\Resource\AbstractStatResource;
use App\Service\Stats;
use App\Traits\CheckExistingResourceTrait;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractDataProvider
{
    use CheckExistingResourceTrait;
    use ToolsTrait;

    public function __construct(private readonly EntityManagerInterface $entityManagerInterface, private readonly Stats $statsService)
    {

    }


    abstract protected function supports(Operation $operation = null): bool;

    protected function getCollection(Operation $operation, array $context = []): array|AbstractStatResource|null
    {

        $result = null;
        $cFilters = $context['filters'] ?? [];

        if (isset($context['uri_variables'])) { // exp. {code} = rvcode

            $filters['is'] = $context['uri_variables']; // available filters

            $journal = $this->statsService->getJournal($context['uri_variables']);

            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $context['uri_variables']['code']));
            }


            $filters['is']['rvid'] = (string)$journal->getRvid();

            $this->addFilters($filters['is'], $operation->getName(), $cFilters);

            if ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][1]) {
                $result = $this->statsService->getSubmissionsStat($filters);

            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][2]) {
                $result = $this->statsService->getDelayBetweenSubmissionAndLatestStatus($filters);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][3]) {
                $result = $this->statsService->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_PUBLISHED);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][0]) {
                $result = $this->statsService->getDashboard($context, $filters);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][4]) {
                $result = $this->statsService->getUserStats($filters['is']);
            }
        }

        if ($result instanceof AbstractStatResource) {
            $result->setRequestedFilters($cFilters);
        }

        return $result;

    }


    private function addFilters(array &$availableFilters, string $operationName, array &$contextFilters = []): void
    {


        if (isset($contextFilters[AppConstants::START_AFTER_DATE])) {
            $startDate = urldecode($contextFilters[AppConstants::START_AFTER_DATE]);


            if (
                self::isValidDate($startDate)) {
                $availableFilters[AppConstants::START_AFTER_DATE] = $startDate;
            }

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
                    in_array($filter, Stats::AVAILABLE_PAPERS_FILTERS, true)
                ) {
                    $availableFilters[$filter] = $value;
                }

            }

        }
    }

}

