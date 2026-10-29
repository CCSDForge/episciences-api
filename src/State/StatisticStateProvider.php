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
use App\Repository\PapersRepository;
use App\Resource\Statistic;
use App\Service\StatisticPapers;
use App\Service\Stats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class StatisticStateProvider extends AbstractStateDataProvider implements ProviderInterface
{
    /** Units accepted by the delay indicators (MySQL TIMESTAMPDIFF units) */
    public const array AVAILABLE_UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'];

    /** Rows of the evaluation queries, by query and filters: the lists of withPapers reuse the rows of the figures */
    private array $evaluationRows = [];

    public function __construct(protected EntityManagerInterface $entityManager, protected LoggerInterface $logger, protected Pagination $pagination, protected Stats $statsService, private readonly StatisticPapers $statisticPapers)
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
        $this->evaluationRows = [];

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
        $groupBy = $this->processGroupBy($filters[Statistic::GROUP_BY_PARAM] ?? null, $operationName, $code);
        // papers hidden from the lists because of a conflict of interest (PAPERID => true); null: no list
        $conflictedPapers = $this->processWithPapers($filters[Statistic::WITH_PAPERS_PARAM] ?? null, $operationName, $journal ?? null, $groupBy);

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

            if ($operationName === '_get_collection' && $groupBy === Statistic::GROUP_BY_JOURNAL) {
                $this->checkIndicators($indicators, Statistic::AVAILABLE_PUBLICATION_INDICATORS);
                $response = $this->getStatisticsByJournal($indicators ?: Statistic::PUBLICATION_INDICATOR_VALUES, $currentFilters, $years, $startAfterDate, $unit);

            } elseif ($operationName === '_get_collection') {
                if ($indicators !== []) { // in the requested order

                    $this->checkIndicators($indicators, Statistic::AVAILABLE_PUBLICATION_INDICATORS);

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
                        ->setName(Statistic::NB_SUBMISSIONS_DETAILS)
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

            } elseif ($groupBy === Statistic::GROUP_BY_JOURNAL) { // evaluations by journal
                $this->checkIndicators($indicators, Statistic::EVAL_INDICATORS);
                $response = $this->getStatisticsByJournal($indicators ?: Statistic::EVAL_INDICATOR_VALUES, $currentFilters, $years, $startAfterDate, $unit);

            } elseif ($indicators !== []) { // evaluations with indicators, in the requested order
                $this->checkIndicators($indicators, Statistic::EVAL_INDICATORS);

                foreach ($indicators as $indicator) {
                    $response[] = new Statistic()->setName($indicator)->setValue($this->getEvaluationStats($currentFilters, $indicator));
                }

            } else {
                $response = $this->getEvalCollection($currentFilters);
            }

            if ($conflictedPapers !== null) {
                $this->addPapers($response, $currentFilters, (int)$rvId, $years, $startAfterDate, $unit, $conflictedPapers);
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
     * "groupBy=journal" returns the statistics of each journal: not with a journal code.
     * @throws ResourceNotFoundException
     */
    private function processGroupBy(mixed $groupBy, string $operationName, ?string $code): ?string
    {
        if ($groupBy === null || $groupBy === '') {
            return null;
        }

        if ($groupBy !== Statistic::GROUP_BY_JOURNAL) {
            throw new ResourceNotFoundException(sprintf('Oops! invalid %s because the available value is: %s', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL));
        }

        if ($operationName !== '_get_collection' && $operationName !== 'evaluation_get_collection') {
            throw new ResourceNotFoundException(sprintf('Oops! %s=%s is only available on %s and %s', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL, Statistic::STATISTIC_GET_COLLECTION_OPERATION_IDENTIFIER, Statistic::EVALUATION_GET_COLLECTION_OPERATION_IDENTIFIER));
        }

        if ($code) {
            throw new ResourceNotFoundException(sprintf('Oops! %s=%s cannot be combined with rvcode', Statistic::GROUP_BY_PARAM, Statistic::GROUP_BY_JOURNAL));
        }

        return $groupBy;
    }

    /**
     * "withPapers": the lists of the papers counted, on the collection of a journal (see StatisticPapers).
     *
     * @return array<int, true>|null the papers not to list (PAPERID => true), null without lists
     * @throws ResourceNotFoundException
     */
    private function processWithPapers(mixed $withPapers, string $operationName, ?Review $journal, ?string $groupBy): ?array
    {
        if (!StatisticPapers::isRequested($withPapers)) {
            return null;
        }

        if (!in_array($operationName, ['_get_collection', 'evaluation_get_collection'], true) || !$journal instanceof Review || $groupBy !== null) {
            throw new ResourceNotFoundException(sprintf('Oops! %s is only available on %s and %s, with rvcode and without %s', Statistic::WITH_PAPERS_PARAM, Statistic::STATISTIC_GET_COLLECTION_OPERATION_IDENTIFIER, Statistic::EVALUATION_GET_COLLECTION_OPERATION_IDENTIFIER, Statistic::GROUP_BY_PARAM));
        }

        return $this->statisticPapers->getConflictedPapers($journal);
    }

    /**
     * Adds to each statistic the papers it counts (one DOCID by paper, its latest version), computed with the same queries
     * as the figures; for the requested and received reviews, the reviews counted (see getReviews()).
     *
     * @param Statistic[] $statistics
     * @param array<int, true> $conflictedPapers papers not to list
     */
    private function addPapers(array $statistics, array $filters, int $rvId, array $years, ?string $startAfterDate, string $unit, array $conflictedPapers): void
    {
        /** @var \App\Repository\PapersRepository $paperRepo */
        $paperRepo = $this->entityManager->getRepository(Paper::class);
        /** @var PaperLogRepository $paperLogRepo */
        $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);
        $common = array_intersect_key($filters, array_flip(['rvid', AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE]));
        $figures = PaperLogRepository::PAPER_IDS_FIGURES;

        foreach ($statistics as $statistic) {
            if (in_array($statistic->getName(), [Statistic::EVAL_INDICATORS['reviews-requested_get'], Statistic::EVAL_INDICATORS['reviews-received_get']], true)) {
                [$reviews, $hidden] = $this->getReviews($statistic->getName(), $filters, $conflictedPapers);
                $statistic->setReviews($reviews)->setHiddenReviews($hidden);
                continue;
            }

            $papers = match ($statistic->getName()) {
                Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'] => $paperRepo->getSubmissionPaperIds(['is' => $filters]),
                Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get'] => [
                    'accepted' => $paperLogRepo->getPaperIds($figures['accepted'], $rvId, $years, $startAfterDate),
                    'refused' => $paperLogRepo->getPaperIds($figures['refused'], $rvId, $years, $startAfterDate),
                ],
                Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'],
                Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get'] => $paperLogRepo->getSubmissionDelayPaperIds($rvId, $years, $startAfterDate, $statistic->getName(), $unit),
                Statistic::NB_SUBMISSIONS_DETAILS => [
                    'nb-submitted-versions' => $paperRepo->getSubmissionPaperIds(['is' => $common], PapersRepository::AVAILABLE_FLAG_VALUES['submitted']),
                    'nb-imported-versions' => $paperRepo->getSubmissionPaperIds(['is' => $common + array_intersect_key($filters, ['status' => true])], PapersRepository::AVAILABLE_FLAG_VALUES['imported']),
                    Paper::STATUS_DICTIONARY[Paper::STATUS_PUBLISHED] => $paperLogRepo->getPaperIds($figures['published'], $rvId, $years, $startAfterDate),
                    Paper::STATUS_DICTIONARY[Paper::STATUS_REFUSED] => $paperLogRepo->getPaperIds($figures['refused'], $rvId, $years, $startAfterDate),
                    'accepted' => $paperLogRepo->getPaperIds($figures['accepted'], $rvId, $years, $startAfterDate),
                    'being-to-publish.accepted' => $paperLogRepo->getPaperIds($figures['accepted-not-yet-published'], $rvId, $years, $startAfterDate),
                    'being-to-publish.other-status' => $paperRepo->getSubmissionsAwaitingDecisionPaperIds($rvId, $startAfterDate, $years),
                ],
                default => null, // the median number of reviews: no list
            };

            if ($papers === null) {
                continue;
            }

            if (array_is_list($papers)) {
                [$docIds, $hidden] = $this->statisticPapers->toDocIds(['papers' => $papers], $conflictedPapers);
                $statistic->setPapers($docIds['papers'])->setHiddenPapers($hidden['papers']);
                continue;
            }

            [$docIds, $hidden] = $this->statisticPapers->toDocIds($papers, $conflictedPapers);
            $statistic->setPapers($docIds)->setHiddenPapers($hidden);
        }
    }

    /**
     * The reviews counted by a figure of the evaluation, computed with the same queries as the figure, without the
     * reviewers: one item by invitation (reviews-requested: date of sending, answer) or by report (reviews-received:
     * date of validation), with the DOCID of the paper version. The reviews of the papers for which the user has declared
     * a conflict of interest are not listed, only counted.
     *
     * @param array<int, true> $conflictedPapers papers not to list (PAPERID => true)
     * @return array{0: list<array{docid: int, date: ?string, answer?: string}>, 1: int} the reviews listed, the number of reviews not listed
     */
    private function getReviews(string $indicator, array $filters, array $conflictedPapers): array
    {
        $isRequested = $indicator === Statistic::EVAL_INDICATORS['reviews-requested_get'];
        $rows = $isRequested
            ? $this->getReviewsRequestedRows($filters)
            : $this->getReceivedReportRows($filters);

        $reviews = [];
        $hidden = 0;
        $now = new \DateTimeImmutable();

        foreach ($rows as $row) {
            // a review whose paper is unknown is not listed either
            if (!isset($row['paperid']) || isset($conflictedPapers[(int)$row['paperid']])) {
                $hidden++;
                continue;
            }

            $review = ['docid' => (int)($isRequested ? $row['itemid'] : $row['docid']), 'date' => self::toDate($row['date'] ?? null)];

            if ($isRequested) {
                $expiration = $row['expirationDate'] ?? null;
                // "expired" is not stored by Episciences: a pending invitation past its expiration date (see UserInvitation)
                $isExpired = $row['status'] === UserInvitation::STATUS_PENDING && $expiration instanceof \DateTimeInterface && $expiration < $now;
                $review['answer'] = $isExpired ? UserInvitation::STATUS_EXPIRED : (string)$row['status'];
            }

            $reviews[] = $review;
        }

        // as the papers, by DOCID; then by date
        usort($reviews, static fn(array $a, array $b): int => [$a['docid'], $a['date']] <=> [$b['docid'], $b['date']]);

        return [$reviews, $hidden];
    }

    private static function toDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return is_string($date) && $date !== '' ? substr($date, 0, 10) : null;
    }

    /**
     * @param string[] $available
     * @throws ResourceNotFoundException
     */
    private function checkIndicators(array $indicators, array $available): void
    {
        foreach ($indicators as $indicator) {
            if (!in_array($indicator, $available, true)) {
                throw new ResourceNotFoundException(sprintf('Oops! invalid indicator "%s" because the available values are: %s', $indicator, implode(', ', $available)));
            }
        }
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
     * The statistics of each journal, computed as for a single journal (one query by indicator for all the journals):
     * one item by journal and by indicator, sorted by journal code, then in the order of the indicators.
     * Only the active journals having data for at least one of the indicators are returned; a missing count is 0, a missing rate or median is null.
     *
     * @param string[] $indicators "evaluation" stands for its three indicators
     * @return Statistic[]
     */
    private function getStatisticsByJournal(array $indicators, array $filters, array $years, ?string $startAfterDate, string $unit): array
    {
        $names = [];

        foreach ($indicators as $indicator) {
            array_push($names, ...($indicator === Statistic::AVAILABLE_PUBLICATION_INDICATORS['evaluation_get_collection'] ? Statistic::EVAL_INDICATOR_VALUES : [$indicator]));
        }

        $names = array_values(array_unique($names));
        $medians = [Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-publication_get'], Statistic::AVAILABLE_PUBLICATION_INDICATORS['median-submission-acceptance_get']];
        $counts = [Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get'], Statistic::EVAL_INDICATORS['reviews-requested_get'], Statistic::EVAL_INDICATORS['reviews-received_get']];

        /** @var PaperLogRepository $paperLogRepo */
        $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);
        $values = []; // indicator => rvid => ['value' => ..., 'count' => ...]
        $evaluation = null;

        foreach ($names as $name) {
            if ($name === Statistic::AVAILABLE_PUBLICATION_INDICATORS['nb-submissions_get']) {
                foreach ($this->entityManager->getRepository(Paper::class)->countSubmissionsByJournal(['is' => $filters]) as $rvId => $total) {
                    $values[$name][$rvId] = ['value' => $total];
                }
            } elseif ($name === Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get']) {
                $accepted = $paperLogRepo->getAcceptedByJournal($years, $startAfterDate);
                $refused = $paperLogRepo->getRefusedByJournal($years, $startAfterDate);

                foreach (array_keys($accepted + $refused) as $rvId) {
                    $values[$name][$rvId] = ['value' => $this->getAcceptanceRate(['acceptedTotal' => $accepted[$rvId] ?? 0, 'refusedTotal' => $refused[$rvId] ?? 0])];
                }
            } elseif (in_array($name, $medians, true)) {
                foreach ($paperLogRepo->getSubmissionMedianTimeDetailsByJournal($years, $startAfterDate, $name, $unit) as $rvId => $details) {
                    $values[$name][$rvId] = ['value' => $details['median'], 'count' => $details['count']];
                }
            } else { // evaluation
                $evaluation ??= $this->getEvaluationStatsByJournal($filters);

                foreach ($evaluation as $rvId => $stats) {
                    $values[$name][$rvId] = ['value' => $stats[$name]];
                }
            }
        }

        $rvIds = [];

        foreach ($values as $byJournal) {
            $rvIds += array_fill_keys(array_keys($byJournal), true);
        }

        if ($rvIds === []) {
            return [];
        }

        $codes = [];

        // active journals only, as with rvcode: the data of a disabled or deleted journal are ignored
        foreach ($this->entityManager->getRepository(Review::class)->findBy(['rvid' => array_keys($rvIds), 'status' => Review::STATUS_ENABLED]) as $journal) {
            $codes[$journal->getRvid()] = $journal->getCode();
        }

        asort($codes, SORT_STRING);
        $response = [];

        foreach ($codes as $rvId => $code) {
            foreach ($names as $name) {
                $data = $values[$name][$rvId] ?? [];
                $statistic = new Statistic()
                    ->setName($name)
                    ->setJournal($code)
                    ->setValue($data['value'] ?? (in_array($name, $counts, true) ? 0 : null));

                if ($name === Statistic::AVAILABLE_PUBLICATION_INDICATORS['acceptance-rate_get']) {
                    $statistic->setUnit('%');
                } elseif (in_array($name, $medians, true)) {
                    $statistic->setUnit(strtolower($unit))->setCount($data['count'] ?? 0);
                }

                $response[] = $statistic;
            }
        }

        return $response;
    }

    /**
     * Same figures as getEvaluationStats(), for each journal (one query by figure for all the journals)
     *
     * @return array<int, array<string, int|float|null>> indexed by journal identifier (rvid), then by evaluation indicator
     */
    private function getEvaluationStatsByJournal(array $options): array
    {
        $requested = [];

        foreach ($this->getReviewsRequestedRows($options) as $row) {
            $requested[(int)$row['rvid']] = ($requested[(int)$row['rvid']] ?? 0) + 1;
        }

        $reports = [];
        $reportsQuery = $this->entityManager->getRepository(ReviewerReport::class)
            ->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))
            ->addSelect('p.rvid AS rvid');

        foreach ($reportsQuery->getQuery()->getArrayResult() as $row) {
            $reports[(int)$row['rvid']][] = $row;
        }

        $byJournal = [];

        foreach (array_keys($requested + $reports) as $rvId) {
            $journalReports = $reports[$rvId] ?? [];

            $byJournal[$rvId] = [
                Statistic::EVAL_INDICATORS['reviews-requested_get'] => $requested[$rvId] ?? 0,
                Statistic::EVAL_INDICATORS['reviews-received_get'] => count($journalReports),
                Statistic::EVAL_INDICATORS['median-reviews-number_get'] => $journalReports === [] ? null : $this->getMedian($this->processResult($journalReports)),
            ];
        }

        return $byJournal;
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
            $result = $this->getReceivedReportRows($options);
            $processed = $this->processResult($result);
            return [
                Statistic::EVAL_INDICATORS['reviews-requested_get'] => count($this->getReviewsRequestedRows($options)),
                Statistic::EVAL_INDICATORS['reviews-received_get'] => count($result),
                Statistic::EVAL_INDICATORS['median-reviews-number_get'] => $this->getMedian($processed),
            ];

        }

        $result = null;

        if ($indicator === Statistic::EVAL_INDICATORS['reviews-requested_get']) {
            $result = count($this->getReviewsRequestedRows($options));

        } elseif ($indicator === Statistic::EVAL_INDICATORS['reviews-received_get']) {
            $result = count($this->getReceivedReportRows($options));


        } elseif ($indicator === Statistic::EVAL_INDICATORS['median-reviews-number_get']) {
            $processed = $this->processResult($this->getReceivedReportRows($options));
            $result = $this->getMedian($processed);

        }

        return $result;

    }

    /**
     * The invitations: one row by invitation (see UserInvitationRepository::getReviewsRequested())
     */
    private function getReviewsRequestedRows(array $options): array
    {
        return $this->evaluationRows['requested' . serialize($options)] ??= $this->entityManager->getRepository(UserInvitation::class)
            ->getReviewsRequested($options)
            ->getQuery()
            ->getResult();
    }

    /**
     * The completed reports: one row by version and reviewer
     */
    private function getReceivedReportRows(array $options): array
    {
        return $this->evaluationRows['received' . serialize($options)] ??= $this->entityManager->getRepository(ReviewerReport::class)
            ->getReceivedReports(array_merge($options, ['report-status' => ReviewerReport::STATUS_COMPLETED]))
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * The number of reports of each paper, all its versions together (the papers without report are not listed).
     * Only the reports of the rows given are counted: with year or startAfterDate, the reports validated in the period,
     * so a paper whose reports were validated in different years counts a part of its reports in each year.
     *
     * @return int[]
     */
    private function processResult(array $result = []): array
    {

        $processed = [];

        foreach ($result as $values) {
            $paperId = $values['paperid'];
            $processed[$paperId] = ($processed[$paperId] ?? 0) + $values['count'];
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

