<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Resource\Statistic;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{

    /**
     * @throws ResourceNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $prefix = '_api_/statistics/';

        $currentFilters = [];

        $filters = $context['filters'] ?? [];
        $code = $filters['rvcode'] ?? null;
        $unit = $filters['unit'] ?? 'week';

        if ($code) {

            $journal = $this->entityManagerInterface->getRepository(Review::class)->findOneBy(['code' => $code]);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

            $currentFilters['rvid'] = $journal->getRvid();
        }

        $operationName = str_replace($prefix, '', $operation->getName());
        $dictionary = array_merge(array_flip(Paper::STATUS_DICTIONARY), ['accepted' => Paper::ACCEPTED_SUBMISSIONS]);
        unset($dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_OBSOLETE]], $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_REMOVED]], $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_DELETED]]);

        $status = isset($filters['status']) ? array_unique((array)$filters['status']) : [];
        $years = isset($filters[AppConstants::YEAR_PARAM]) ? array_unique((array)$filters[AppConstants::YEAR_PARAM]) : null;
        $flag = $filters['flag'] ?? null;
        $startAfterDate = $filters['startAfterDate'] ?? null;

        $isPaginationEnabled = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);
        $page = $context['filters']['page'] ?? 1;
        $firstResult = 0;
        $indicator = $filters['indicator'] ?? null;

        foreach ($status as $currentStatus) {
            $toLowerStatus = strtolower($currentStatus);
            if (!array_key_exists($toLowerStatus, $dictionary)) {
                $message = sprintf('Oops! invalid status: "%s" because the available values are: %s ', $currentStatus, implode(', ', array_keys($dictionary)));
                throw new ResourceNotFoundException($message);
            }

            $currentFilters['status'] = array_merge($currentFilters['status'] ?? [], (array)$dictionary[$toLowerStatus]);
        }

        if ($years) {
            $currentFilters[AppConstants::YEAR_PARAM] = $years;
        }

        if ($flag) {
            $currentFilters['flag'] = $flag;
        }

        if ($startAfterDate) {
            $currentFilters['startAfterDate'] = $startAfterDate;
        }

        if ($operationName === '_get_collection') {
            $response = [];

            if ($indicator) {

                if (!in_array($indicator, Statistic::AVAILABLE_INDICATORS, true)) {
                    throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', Statistic::AVAILABLE_INDICATORS)));
                }

                if ($indicator === Statistic::AVAILABLE_INDICATORS['nb-submissions_get']) {
                    $nbSubmissionStatsQuery = $this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
                    $response[] = (new Statistic())
                        ->setName($indicator)
                        ->setValue((float)$nbSubmissionStatsQuery->getSingleScalarResult());
                } elseif ($indicator === Statistic::AVAILABLE_INDICATORS['acceptance-rate_get']) {

                    $response[] = (new Statistic())
                        ->setName($indicator)
                        ->setValue($this->getAcceptanceRate($currentFilters))
                        ->setUnit('%');


                } elseif ($indicator === Statistic::AVAILABLE_INDICATORS['median-submission-publication_get'] || $indicator === Statistic::AVAILABLE_INDICATORS['median-submission-acceptance_get']) {
                    $response[] = (new Statistic())
                        ->setName(Statistic::AVAILABLE_INDICATORS[$indicator])
                        ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, $indicator, $unit))
                        ->setUnit(strtolower($unit));
                }

            } else {
                $nbSubmissionStatsQuery = $this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
                $response[] = (new Statistic())
                    ->setName(Statistic::AVAILABLE_INDICATORS['nb-submissions_get'])
                    ->setValue((float)$nbSubmissionStatsQuery->getSingleScalarResult());

                $response[] = (new Statistic())
                    ->setName(Statistic::AVAILABLE_INDICATORS['acceptance-rate_get'])
                    ->setValue($this->getAcceptanceRate($currentFilters))
                    ->setUnit('%');


                $response[] = (new Statistic())
                    ->setName(Statistic::AVAILABLE_INDICATORS['median-submission-publication_get'])
                    ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_INDICATORS['median-submission-publication_get'], $unit))
                    ->setUnit(strtolower($unit));
                $response[] = (new Statistic())
                    ->setName(Statistic::AVAILABLE_INDICATORS['median-submission-acceptance_get'])
                    ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_INDICATORS['median-submission-acceptance_get'], $unit))
                    ->setUnit(strtolower($unit));

            }

            $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: 30;

            if ($isPaginationEnabled) {
                $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
                $firstResult = ($page - 1) * $maxResults;
            }

            return new ArrayPaginator($response, $firstResult, $maxResults);

        }

        if (!array_key_exists($operationName, Statistic::AVAILABLE_INDICATORS)) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid operation: %s', $operation->getUriTemplate()));
        }

        $oStats = (new Statistic())->setName(Statistic::AVAILABLE_INDICATORS[$operationName]);
        $statsQuery = null;

        if ($operationName === 'nb-submissions_get') {
            $statsQuery = $this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
        } elseif ($operationName === 'median-submission-publication_get' || $operationName === 'median-submission-acceptance_get') {
            return $oStats
                ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_INDICATORS[$operationName], $unit))
                ->setUnit(strtolower($unit));
        } elseif ($operationName === 'acceptance-rate_get') {
            return $oStats->setValue($this->getAcceptanceRate($currentFilters));
        }

        $result = $statsQuery ? (int)$statsQuery->getSingleScalarResult() : 0;

        return $oStats->setValue($result);

    }


    private function getAcceptanceRate(array $options = []): float|null
    {

        $years = $options['year'] ?? [];
        $rvId = $options['rvid'] ?? null;
        $flag = PapersRepository::AVAILABLE_FLAG_VALUES['submitted'];
        $startAfterDate = $options['startAfterDate'] ?? null;
        $params = ['rvid' => $rvId, 'startAfterDate' => $startAfterDate, 'flag' => $flag, 'year' => $years];

        $allSubmissions = (float)$this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $params])->getQuery()->getSingleScalarResult();

        if (!$allSubmissions) {
            return null;
        }

        $allaAcceptedArticle = $this->entityManagerInterface->getRepository(PaperLog::class)->getNumberOfAcceptedArticlesQuery($rvId, $years, $startAfterDate);

        return round(($allaAcceptedArticle / $allSubmissions) * 100, 2);

    }
}
