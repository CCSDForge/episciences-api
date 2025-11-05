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
use App\Entity\UserInvitation;
use App\Exception\ResourceNotFoundException;
use App\Repository\PapersRepository;
use App\Resource\Statistic;
use App\Service\Stats;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, protected Stats $statsService)
    {
        parent::__construct($entityManager, $logger);
    }

    use ToolsTrait;

    /**
     * @throws ResourceNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        $prefix = '_api_/statistics/';

        $currentFilters = [];

        $this->checkAndProcessFilters($context);

        $filters = $context['filters'] ?? [];
        $code = $filters['rvcode'] ?? null;
        $unit = $filters['unit'] ?? 'week';
        $rvId = null;

        if ($code) {

            $journal = $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($code);
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
        $availableYears = $this->entityManager->getRepository(Paper::class)->getYearRange($rvId);

        $yearsDiff = $this->checkArrayEquality($availableYears, $years);

        if (!empty($yearsDiff['arrayDiff']['out'])) {
            $message = sprintf('Oops! invalid year(s): %s because the available values are [%s]', implode(', ', $yearsDiff['arrayDiff']['out']), implode(', ', $availableYears));
            throw new ResourceNotFoundException($message);
        }

        $flag = $filters['flag'] ?? null;
        $startAfterDate = $filters['startAfterDate'] ?? null;

        $isPaginationEnabled = $context['filters']['pagination'];
        $page = $context['filters']['page'];
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

        if ($operationName === '_get_collection' || $operationName === 'evaluation_get_collection') {
            $response = [];

            if ($operationName === '_get_collection') {
                if ($indicator) {

                    if (!in_array($indicator, Statistic::AVAILABLE_PUBLICATION_INDICATORS, true)) {
                        throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', Statistic::AVAILABLE_PUBLICATION_INDICATORS)));
                    }

                    if ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get']) {
                        $nbSubmissionStatsQuery = $this->entityManager->getRepository(Paper::class)
                            ->submissionsQuery(['is' => $currentFilters])
                            ->getQuery();
                        $response[] = (new Statistic())
                            ->setName($indicator)
                            ->setValue($nbSubmissionStatsQuery->getSingleScalarResult());
                    } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get']) {

                        $response[] = (new Statistic())
                            ->setName($indicator)
                            ->setValue($this->getAcceptanceRate($currentFilters))
                            ->setUnit('%');


                    } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'] || $indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get']) {
                        $response[] = (new Statistic())
                            ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS[sprintf('%s_get', $indicator)])
                            ->setValue($this->entityManager->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, $indicator, $unit))
                            ->setUnit(strtolower($unit));
                    } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection']) {
                        $response = $this->getEvalCollection($currentFilters);
                    }

                } else { // all submissions indicators

                    $paperRepo = $this->entityManager->getRepository(Paper::class);
                    $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);

                    $imported = $paperRepo->submissionsQuery(['is' => $currentFilters], false, 'submissionDate', false, PapersRepository::AVAILABLE_FLAG_VALUES['imported'])
                        ->getQuery()
                        ->getSingleScalarResult();


                    $nbSubmissionStatsQuery = $paperRepo->submissionsQuery(['is' => $currentFilters])->getQuery();
                    $nbSubmission = $nbSubmissionStatsQuery->getSingleScalarResult();

                    $nbSubmissionsWithoutImported = $paperRepo->getSubmissionsWithoutImported($rvId, $startAfterDate, $years);

                    // stats from papers logs
                    $nbPublished = $paperLogRepo->getPublished($rvId, $years, $startAfterDate);
                    $nbRefused = $paperLogRepo->getRefused($rvId, $years, $startAfterDate);
                    $nbAccepted = $paperLogRepo->getAccepted($rvId, $years, $startAfterDate);

                    $response[] = (new Statistic())
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'])
                        ->setValue($nbSubmission);

                    $response[] = (new Statistic())
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get'])
                        ->setValue($this->getAcceptanceRate($currentFilters))
                        ->setUnit('%');


                    $response[] = (new Statistic())
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'])
                        ->setValue($paperLogRepo->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'], $unit))
                        ->setUnit(strtolower($unit));
                    $response[] = (new Statistic())
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get'])
                        ->setValue($paperLogRepo->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get'], $unit))
                        ->setUnit(strtolower($unit));


                    $response[] = (new Statistic())
                        ->setName('nb-submissions-details')
                        ->setValue(
                            [
                                'imported' => $imported,
                                Paper::STATUS_DICTIONARY[Paper::STATUS_PUBLISHED] => $nbPublished,
                                Paper::STATUS_DICTIONARY[Paper::STATUS_REFUSED] => $nbRefused,
                                'accepted' => $nbAccepted, // include published
                                'being-to-publish' => [
                                    'accepted' => $paperLogRepo->getAllAcceptedNotYetPublished($rvId, $years, $startAfterDate),
                                    'other-status' => max(0, $nbSubmissionsWithoutImported - ($nbAccepted + $nbRefused))
                                ]
                            ]);

                    $response[] = (new Statistic())->setName('evaluation')->setValue($this->getEvaluationStats($currentFilters));
                }

            } elseif ($indicator) {// evaluations With indicators

                if (!in_array($indicator, Statistic::EVAL_INDICATORS, true)) {
                    throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', Statistic::EVAL_INDICATORS)));
                }

                $eval = $this->getEvaluationStats($currentFilters, $indicator);

                if ($indicator === Statistic::EVAL_INDICATORS['median-reviews-number_get']) {
                    $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['median-reviews-number_get'])->setValue($eval);

                } elseif ($indicator === Statistic::EVAL_INDICATORS['reviews-requested_get']) {
                    $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['reviews-requested_get'])->setValue($eval);

                } elseif ($indicator === Statistic::EVAL_INDICATORS['reviews-received_get']) {
                    $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['reviews-received_get'])->setValue($eval);
                }

            } else {
                $response = $this->getEvalCollection($currentFilters);
            }

            $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: 30;

            if ($isPaginationEnabled) {
                $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
                $firstResult = ($page - 1) * $maxResults;
            }

            $paginator = new ArrayPaginator($response, $firstResult, $maxResults);
            $this->checkSeekPosition($paginator, $maxResults);
            return $paginator;

        }


        if (!array_key_exists($operationName, Statistic::AVAILABLE_PUBLICATION_INDICATORS)) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid operation: %s', $operation->getUriTemplate()));
        }

        $oStats = (new Statistic())->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS[$operationName]);
        $statsQuery = null;

        if ($operationName === 'nb-submissions_get') {
            $statsQuery = $this->entityManager->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
        } elseif ($operationName === 'median-submission-publication_get' || $operationName === 'median-submission-acceptance_get') {
            return $oStats
                ->setValue($this->entityManager->getRepository(PaperLog::class)->getSubmissionMedianTimeByStatusQuery($currentFilters['rvid'] ?? null, $years, $startAfterDate, Statistic::AVAILABLE_PUBLICATION_INDICATORS[$operationName], $unit))
                ->setUnit(strtolower($unit));
        } elseif ($operationName === 'acceptance-rate_get') {
            return $oStats->setValue($this->getAcceptanceRate($currentFilters))->setUnit('%');
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
        $paperRepo = $this->entityManager->getRepository(Paper::class);

        $allSubmissionsWithoutImported = $paperRepo->submissionsQuery(['is' => $params])->getQuery()->getSingleScalarResult();

        if (!$allSubmissionsWithoutImported) {
            return null;
        }


        $allAcceptedArticle = $this->entityManager->getRepository(PaperLog::class)->getAccepted($rvId, $years, $startAfterDate);

        return round(($allAcceptedArticle / $allSubmissionsWithoutImported) * 100, AppConstants::RATE_DEFAULT_PRECISION, PHP_ROUND_HALF_UP);

    }


    private function getEvaluationStats(array $options = [], string $indicator = null): null|float|array
    {
        if (!$indicator) {
            $result = $this->entityManager->getRepository(ReviewerReport::class)->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))->getQuery()->getArrayResult();
            $processed = $this->processResult($result);
            try {
                $medianReviewsNumber = $this->getMedian($processed);
            } catch (\LengthException $e) {
                $this->logger->critical($e->getMessage());
                $medianReviewsNumber = null;
            }
            return [
                Statistic::EVAL_INDICATORS['reviews-requested_get'] => count($this->entityManager->getRepository(UserInvitation::class)->getReviewsRequested($options)->getQuery()->getResult()),
                Statistic::EVAL_INDICATORS['reviews-received_get'] => count($result),
                Statistic::EVAL_INDICATORS['median-reviews-number_get'] => $medianReviewsNumber,
            ];

        }

        $result = null;

        if ($indicator === Statistic::EVAL_INDICATORS['reviews-requested_get']) {
            $result = count($this->entityManager->getRepository(UserInvitation::class)->getReviewsRequested($options)->getQuery()->getResult());

        } elseif ($indicator === Statistic::EVAL_INDICATORS['reviews-received_get']) {
            $result = count($this->entityManager->getRepository(ReviewerReport::class)->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))->getQuery()->getArrayResult());


        } elseif ($indicator === Statistic::EVAL_INDICATORS['median-reviews-number_get']) {
            $processed = $this->processResult($this->entityManager->getRepository(ReviewerReport::class)->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))->getQuery()->getArrayResult());
            try {
                $medianReviewsNumber = $this->getMedian($processed);
            } catch (\LengthException $e) {
                $this->logger->critical($e->getMessage());
                $medianReviewsNumber = null;
            }
            $result = $medianReviewsNumber;

        }

        return $result;

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

    private function getEvalCollection(array $currentFilters): array
    {
        $eval = $this->getEvaluationStats($currentFilters);
        $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['reviews-requested_get'])->setValue($eval[Statistic::EVAL_INDICATORS['reviews-requested_get']]);
        $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['reviews-received_get'])->setValue($eval[Statistic::EVAL_INDICATORS['reviews-received_get']]);
        $response[] = (new Statistic())->setName(Statistic::EVAL_INDICATORS['median-reviews-number_get'])->setValue($eval[Statistic::EVAL_INDICATORS['median-reviews-number_get']]);
        return $response;

    }
}

