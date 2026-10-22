<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Paper;
use App\Entity\PaperLog;
use App\Entity\Review;
use App\Entity\ReviewerReport;
use App\Entity\UserInvitation;
use App\Exception\ResourceNotFoundException;
use App\Repository\PaperLogRepository;
use App\Resource\Statistic;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    /** Units accepted by the delay indicators (MySQL TIMESTAMPDIFF units) */
    public const array AVAILABLE_UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'];

    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, protected Pagination $pagination, protected Stats $statsService)
    {
        parent::__construct($entityManager, $logger, $pagination);
    }

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
        // An empty unit (?unit=) falls back to the default one
        $unit = $this->processUnit(($filters['unit'] ?? '') ?: 'week');
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
        $startAfterDate = $this->processStartAfterDate($filters[AppConstants::START_AFTER_DATE] ?? null);

        $isPaginationEnabled = $context['filters']['pagination'];
        $page = $context['filters']['page'];
        $firstResult = 0;
        $indicators = $this->processIndicators($filters['indicator'] ?? null);
        $groupBy = $this->processGroupBy($filters[Statistic::GROUP_BY_PARAM] ?? null, $operationName, $indicators, $code);

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

        if ($years !== []) {
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
                if ($indicators !== []) { // in the requested order

                    foreach ($indicators as $indicator) {
                        if (!in_array($indicator, Statistic::AVAILABLE_PUBLICATION_INDICATORS, true)) {
                            throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', Statistic::AVAILABLE_PUBLICATION_INDICATORS)));
                        }
                    }

                    foreach ($indicators as $indicator) {
                        if ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get']) {
                            $nbSubmissionStatsQuery = $this->entityManager->getRepository(Paper::class)
                                ->submissionsQuery(['is' => $currentFilters])
                                ->getQuery();
                            $response[] = new Statistic()
                                ->setName($indicator)
                                ->setValue($nbSubmissionStatsQuery->getSingleScalarResult());
                        } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get']) {
                            $response[] = new Statistic()
                                ->setName($indicator)
                                ->setValue($this->getAcceptanceRate($currentFilters))
                                ->setUnit('%');
                        } elseif ($groupBy === Statistic::GROUP_BY_JOURNAL) {
                            $response = $this->getMedianDelayByJournal($indicator, $years, $startAfterDate, $unit);
                        } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'] || $indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get']) {
                            $response[] = $this->getMedianDelay($indicator, $rvId, $years, $startAfterDate, $unit);
                        } elseif ($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection']) {
                            array_push($response, ...$this->getEvalCollection($currentFilters));
                        }
                    }

                } else { // all submissions indicators

                    $paperRepo = $this->entityManager->getRepository(Paper::class);
                    $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);

                    // submissions, imported and submitted papers: a single query
                    $submissions = $paperRepo->countSubmissionsByFlag($currentFilters);
                    $nbSubmission = $submissions['total'];
                    $imported = $submissions['imported'];
                    $nbSubmissionsWithoutImported = $submissions['submitted'];

                    // stats from papers logs
                    $nbPublished = $paperLogRepo->getPublished($rvId, $years, $startAfterDate);
                    $nbRefused = $paperLogRepo->getRefused($rvId, $years, $startAfterDate);
                    $nbAccepted = $paperLogRepo->getAccepted($rvId, $years, $startAfterDate);

                    $response[] = new Statistic()
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'])
                        ->setValue($nbSubmission);

                    $response[] = new Statistic()
                        ->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get'])
                        // Totals already computed above: avoids counting them again
                        ->setValue($this->getAcceptanceRate(array_merge($currentFilters, ['acceptedTotal' => $nbAccepted, 'refusedTotal' => $nbRefused])))
                        ->setUnit('%');


                    $response[] = $this->getMedianDelay(Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'], $rvId, $years, $startAfterDate, $unit);
                    $response[] = $this->getMedianDelay(Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get'], $rvId, $years, $startAfterDate, $unit);


                    $response[] = new Statistic()
                        ->setName('nb-submissions-details')
                        ->setValue(
                            [
                                'nb-submitted-versions' => $nbSubmissionsWithoutImported,
                                'nb-imported-versions' => $imported,
                                Paper::STATUS_DICTIONARY[Paper::STATUS_PUBLISHED] => $nbPublished,
                                Paper::STATUS_DICTIONARY[Paper::STATUS_REFUSED] => $nbRefused,
                                'accepted' => $nbAccepted, // include published
                                'being-to-publish' => [
                                    'accepted' => $paperLogRepo->getAllAcceptedNotYetPublished($rvId, $years, $startAfterDate),
                                    'other-status' => $paperRepo->getSubmissionsAwaitingDecision($rvId, $startAfterDate, $years)
                                ]
                            ]);

                    $response[] = new Statistic()->setName('evaluation')->setValue($this->getEvaluationStats($currentFilters));
                }

            } elseif ($indicators !== []) { // evaluations with indicators, in the requested order

                foreach ($indicators as $indicator) {
                    if (!in_array($indicator, Statistic::EVAL_INDICATORS, true)) {
                        throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', Statistic::EVAL_INDICATORS)));
                    }
                }

                foreach ($indicators as $indicator) {
                    $response[] = new Statistic()->setName($indicator)->setValue($this->getEvaluationStats($currentFilters, $indicator));
                }

            } else {
                $response = $this->getEvalCollection($currentFilters);
            }

            $maxResults = $operation->getPaginationMaximumItemsPerPage() ?: 30;

            $this->flagIfTruncated($context, count($response), $maxResults);

            if ($isPaginationEnabled) {
                $maxResults = $context['filters']['itemsPerPage'] ?? $maxResults;
                $firstResult = ($page - 1) * $maxResults;
            }

            $paginator = new ArrayPaginator($response, $firstResult, $maxResults);
            $this->checkSeekPosition($paginator);
            return $paginator;

        }


        if (!array_key_exists($operationName, Statistic::AVAILABLE_PUBLICATION_INDICATORS)) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid operation: %s', $operation->getUriTemplate()));
        }

        $oStats = new Statistic()->setName(Statistic::AVAILABLE_PUBLICATION_INDICATORS[$operationName]);
        $statsQuery = null;

        if ($operationName === 'nb-submissions_get') {
            $statsQuery = $this->entityManager->getRepository(Paper::class)->submissionsQuery(['is' => $currentFilters])->getQuery();
        } elseif ($operationName === 'median-submission-publication_get' || $operationName === 'median-submission-acceptance_get') {
            return $this->getMedianDelay(Statistic::AVAILABLE_PUBLICATION_INDICATORS[$operationName], $rvId, $years, $startAfterDate, $unit);
        } elseif ($operationName === 'acceptance-rate_get') {
            return $oStats->setValue($this->getAcceptanceRate($currentFilters))->setUnit('%');
        }

        $result = $statsQuery ? (int)$statsQuery->getSingleScalarResult() : 0;

        return $oStats->setValue($result);

    }


    /**
     * One or several indicators, sent as "indicator" or "indicator[]" (duplicates and empty values ignored).
     *
     * @return list<string>
     * @throws ResourceNotFoundException
     */
    private function processIndicators(mixed $indicator): array
    {
        $indicators = is_array($indicator) ? $indicator : [$indicator];
        $indicators = array_values(array_unique(array_filter($indicators, static fn($value) => $value !== '' && $value !== null)));

        foreach ($indicators as $value) {
            if (!is_string($value)) {
                throw new ResourceNotFoundException('Oops! invalid indicator');
            }
        }

        return $indicators;
    }

    /**
     * "groupBy=journal" is only available for the median delays of all the journals.
     * @throws ResourceNotFoundException
     */
    private function processGroupBy(mixed $groupBy, string $operationName, array $indicators, ?string $code): ?string
    {
        if ($groupBy === null || $groupBy === '') {
            return null;
        }

        if ($groupBy !== Statistic::GROUP_BY_JOURNAL) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid %s because the available value is: %s', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL));
        }

        $medianIndicators = [Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'], Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get']];

        // a single median delay: the items are the journals
        if ($operationName !== '_get_collection' || count($indicators) !== 1 || !in_array($indicators[0], $medianIndicators, true)) {
            throw new ResourceNotFoundException(sprintf('Oops! %s=%s requires indicator=%s', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL, implode(' or indicator=', $medianIndicators)));
        }

        if ($code) {
            throw new ResourceNotFoundException(sprintf('Oops! %s=%s cannot be combined with rvcode', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL));
        }

        return $groupBy;
    }

    private function getMedianDelay(string $indicator, ?int $rvId, array $years, ?string $startAfterDate, string $unit): Statistic
    {
        $details = $this->entityManager->getRepository(PaperLog::class)->getSubmissionMedianTimeDetails($rvId, $years, $startAfterDate, $indicator, $unit);

        return new Statistic()
            ->setName($indicator)
            ->setValue($details['median'])
            ->setUnit(strtolower($unit))
            ->setCount($details['count']);
    }

    /**
     * @return Statistic[] one median delay by journal having at least one article, sorted by journal code
     */
    private function getMedianDelayByJournal(string $indicator, array $years, ?string $startAfterDate, string $unit): array
    {
        $detailsByJournal = $this->entityManager->getRepository(PaperLog::class)->getSubmissionMedianTimeDetailsByJournal($years, $startAfterDate, $indicator, $unit);

        if ($detailsByJournal === []) {
            return [];
        }

        $codes = [];

        foreach ($this->entityManager->getRepository(Review::class)->findBy(['rvid' => array_keys($detailsByJournal)]) as $journal) {
            $codes[$journal->getRvid()] = $journal->getCode();
        }

        $response = [];

        foreach ($detailsByJournal as $rvId => $details) {
            if (!isset($codes[$rvId])) { // log of a journal that no longer exists
                continue;
            }

            $response[] = new Statistic()
                ->setName($indicator)
                ->setJournal($codes[$rvId])
                ->setValue($details['median'])
                ->setUnit(strtolower($unit))
                ->setCount($details['count']);
        }

        usort($response, static fn(Statistic $a, Statistic $b): int => strcmp((string)$a->getJournal(), (string)$b->getJournal()));

        return $response;
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function processStartAfterDate(mixed $startAfterDate): ?string
    {
        if ($startAfterDate === null || $startAfterDate === '') {
            return null;
        }

        $date = is_string($startAfterDate) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $startAfterDate) : false;

        if (!$date || $date->format('Y-m-d') !== $startAfterDate) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid %s: the expected format is YYYY-MM-DD', AppConstants::START_AFTER_DATE));
        }

        return $startAfterDate;
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function processUnit(mixed $unit): string
    {
        if (!is_string($unit) || !in_array(strtolower($unit), self::AVAILABLE_UNITS, true)) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid unit because the available values are: %s', implode(', ', self::AVAILABLE_UNITS)));
        }

        return strtolower($unit);
    }

    public function getAcceptanceRate(array $options = []): float|null
    {
        /** @var PaperLogRepository $logRepository */
        $logRepository = $this->entityManager->getRepository(PaperLog::class);
        return $logRepository->getAcceptanceRate($options);
    }

    private function getEvaluationStats(array $options = [], ?string $indicator = null): null|float|array
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
            $processed[$docId] = isset($processed[$docId]) ? $processed[$docId] + $values['count'] : ($values['count']);
        }

        return array_values($processed);

    }

    private function getEvalCollection(array $currentFilters): array
    {
        $eval = $this->getEvaluationStats($currentFilters);
        $response[] = new Statistic()->setName(Statistic::EVAL_INDICATORS['reviews-requested_get'])->setValue($eval[Statistic::EVAL_INDICATORS['reviews-requested_get']]);
        $response[] = new Statistic()->setName(Statistic::EVAL_INDICATORS['reviews-received_get'])->setValue($eval[Statistic::EVAL_INDICATORS['reviews-received_get']]);
        $response[] = new Statistic()->setName(Statistic::EVAL_INDICATORS['median-reviews-number_get'])->setValue($eval[Statistic::EVAL_INDICATORS['median-reviews-number_get']]);
        return $response;

    }
}

