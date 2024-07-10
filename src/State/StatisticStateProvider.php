<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\ReviewerReport;
use App\Entity\UserAssignment;
use App\Entity\UserInvitation;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Resource\Statistic;
use App\Traits\ToolsTrait;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    use ToolsTrait;

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
        $rvId = null;

        if ($code) {

            $journal = $this->entityManagerInterface->getRepository(Review::class)->findOneBy(['code' => $code]);
            if (!$journal) {
                throw new ResourceNotFoundException(sprintf('Oops! not found Journal %s', $code));
            }

            $rvId = $journal->getRvid();
            $currentFilters['rvid'] = $rvId;
        }

        $operationName = str_replace($prefix, '', $operation->getName());
        $dictionary = array_merge(array_flip(Paper::STATUS_DICTIONARY), ['accepted' => Paper::STATUS_ACCEPTED]);
        unset($dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_OBSOLETE]], $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_REMOVED]], $dictionary[Paper::STATUS_DICTIONARY[Paper::STATUS_DELETED]]);

        $status = isset($filters['status']) ? array_unique((array)$filters['status']) : [];

        $years = isset($filters[AppConstants::YEAR_PARAM]) ? array_unique((array)$filters[AppConstants::YEAR_PARAM]) : [];
        $availableYears = $this->entityManagerInterface->getRepository(Paper::class)->getYearRange($rvId);

        $yearsDiff = $this->checkArrayEquality($availableYears, $years);

        if (!empty($yearsDiff['arrayDiff']['out'])) {
            $message = sprintf('Oops! invalid year(s): %s because the available values are [%s]', implode(', ', $yearsDiff['arrayDiff']['out']), implode(', ', $availableYears));
            throw new ResourceNotFoundException($message);
        }

        $flag = $filters['flag'] ?? null;
        $startAfterDate = $filters['startAfterDate'] ?? null;

        $isPaginationEnabled = !isset($context['filters']['pagination']) || filter_var($context['filters']['pagination'], FILTER_VALIDATE_BOOLEAN);
        $page = $context['filters']['page'] ?? 1;
        $firstResult = 0;
        $indicator = $filters['indicator'] ?? null;

        $dictionaryKeys = array_keys($dictionary);
        $statusValues = array_values($status);

        $statusDiff = $this->checkArrayEquality($dictionaryKeys, $statusValues);

        if (!empty($statusDiff['arrayDiff']['out'])) {
            $message = sprintf('Oops! invalid status: "%s" because the available values are: [%s]', implode(', ', $statusDiff['arrayDiff']['out']), implode(', ', $dictionaryKeys));
            throw new ResourceNotFoundException($message);
        }

        foreach ($status as $currentStatus) {
            $currentFilters['status'] = array_merge($currentFilters['status'] ?? [], (array)$dictionary[$currentStatus]);
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

            } else { // all indicators
//                $nbSubmissionStatsQuery = $this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
//                $nbSubmission = (float)$nbSubmissionStatsQuery->getSingleScalarResult();
//                $nbPublished = (float)$this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => array_merge(['status' => Paper::STATUS_PUBLISHED], $currentFilters)])->getQuery()->getSingleScalarResult();
//                $nbRefused = (float)$this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => array_merge(['status' => Paper::STATUS_REFUSED], $currentFilters)])->getQuery()->getSingleScalarResult();
//                $nbAccepted = (float)$this->entityManagerInterface->getRepository(Paper::class)->submissionsQuery(['is' => array_merge(['status' => Paper::STATUS_ACCEPTED], $currentFilters)])->getQuery()->getSingleScalarResult();
//
//                $response[] = (new Statistic())
//                    ->setName(Statistic::AVAILABLE_INDICATORS['nb-submissions_get'])
//                    ->setValue($nbSubmission);
//
//                $response[] = (new Statistic())
//                    ->setName(Statistic::AVAILABLE_INDICATORS['acceptance-rate_get'])
//                    ->setValue($this->getAcceptanceRate($currentFilters))
//                    ->setUnit('%');
//
//
//                $response[] = (new Statistic())
//                    ->setName(Statistic::AVAILABLE_INDICATORS['median-submission-publication_get'])
//                    ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_INDICATORS['median-submission-publication_get'], $unit))
//                    ->setUnit(strtolower($unit));
//                $response[] = (new Statistic())
//                    ->setName(Statistic::AVAILABLE_INDICATORS['median-submission-acceptance_get'])
//                    ->setValue($this->entityManagerInterface->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_INDICATORS['median-submission-acceptance_get'], $unit))
//                    ->setUnit(strtolower($unit));
//
//                $response[] = (new Statistic())
//                    ->setName('nb-submissions-details')
//                    ->setValue([
//                        Paper::STATUS_DICTIONARY[Paper::STATUS_PUBLISHED] => $nbPublished,
//                        Paper::STATUS_DICTIONARY[Paper::STATUS_REFUSED] => $nbRefused,
//                        'being-to-publish' => [
//                            'accepted' => $nbAccepted,
//                            'other-status' => $nbSubmission - ($nbPublished + $nbRefused + $nbAccepted),
//                        ]
//                    ]);

                $response[] = (new Statistic())->setName('evaluation')->setValue($this->getEvaluationStats($currentFilters));

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
        } elseif ($operationName === 'reviews-received_get') {

        } elseif ($operationName === 'reviews-requested_get') {

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

        $allAcceptedArticle = $this->entityManagerInterface->getRepository(PaperLog::class)->getNumberOfAcceptedArticlesQuery($rvId, $years, $startAfterDate);

        return round(($allAcceptedArticle / $allSubmissions) * 100, 2);

    }


    private function getEvaluationStats(array $options = []): array
    {
        $result = $this->entityManagerInterface->getRepository(ReviewerReport::class)->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))->getQuery()->getArrayResult();
        $processed = $this->processResult($result);

        return [
            'review-requested' => count($this->entityManagerInterface->getRepository(UserInvitation::class)->getReviewsRequested($options)->getQuery()->getResult()),
            'review-received' => count($result),
            'median-reviews-number' => $this->getMedian($processed),
        ];

    }

    private function processResult(array $result = []): array
    {

        $processed = [];

        foreach ($result as $values) {
            $docId = $values['docid'];
            $processed[$docId] = !isset($processed[$docId]) ? $values['count'] : ($processed[$docId] + $values['count']);
        }

        return array_values($processed);

    }
}

