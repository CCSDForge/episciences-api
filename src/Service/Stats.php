<?php

namespace App\Service;

use App\AppConstants;
use App\DataProvider\ReviewStatsDataProvider;
use App\Entity\PaperLog;
use App\Entity\Paper;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PaperLogRepository;
use App\Repository\PapersRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Resource\AbstractStatResource;
use App\Resource\DashboardOutput;
use App\Resource\SubmissionAcceptanceDelayOutput;
use App\Resource\SubmissionOutput;
use App\Resource\SubmissionPublicationDelayOutput;
use App\Resource\UsersStatsOutput;
use App\Traits\ToolsTrait;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Psr\Log\LoggerInterface;

class Stats
{
    use ToolsTrait;

    public const SUBMISSIONS_BY_YEAR = 'submissionsByYear';
    public const STATS_UNIT = 'unit';
    public const STATS_METHOD = 'method';
    public const TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR = 'acceptedSubmittedSameYear';
    public const TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR = 'publishedSubmittedSameYear';
    public const TOTAL_REFUSED_SUBMITTED_SAME_YEAR = 'refusedSubmittedSameYear';
    public const ACCEPTANCE_RATE = 'acceptanceRate'; // TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR / submissions by year
    public const REF_YEAR = 2013; // statistics since this year
    public const AVAILABLE_PAPERS_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::PAPER_FLAG, AppConstants::PAPER_STATUS, AppConstants::YEAR_PARAM, AppConstants::START_AFTER_DATE];
    public const AVAILABLE_USER_ROLES_FILTERS = ['rvid', 'uid', 'role'];
    public const AVAILABLE_USERS_FILTERS = [AppConstants::WITH_DETAILS];
    public const DEFAULT_METHOD = 'average';
    public const MEDIAN_METHOD = 'median';

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly MetadataSources $metadataSources, private readonly LoggerInterface $logger)
    {

    }

    public function getDashboard(array $context, array $filters): DashboardOutput
    {

        $years = isset($filters['is']['submissionDate']) ? (array)$filters['is']['submissionDate'] : [];
        $rvId = $filters['is']['rvid'] ?? null;
        $startAfterDate = $filters['is']['startAfterDate'] ?? null;

        $result = new DashboardOutput();

        /** @var PaperLogRepository $paperLogRepo */
        $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);

        /** @var PapersRepository $paperRepo */
        $paperRepo = $this->entityManager->getRepository(Paper::class);

        $submissions = $this->getSubmissionsStat($filters);
        $nbSubmissions = $submissions->getValue(); // imported submissions included

        if (!$nbSubmissions) {
            return $result;
        }

        // aggregate stats

        if ($startAfterDate && !$years) { // StartAfterDate Filter ignored: they provide an overview of the data, without taking this filter into account.
            $values['totalWithoutStartAfterDate']['totalSubmissions'] = $paperRepo->submissionsQuery(['is' => ['rvid' => $rvId]])->getQuery()->getSingleScalarResult();
            $values['totalWithoutStartAfterDate']['totalImported'] = $paperRepo->submissionsQuery(['is' => ['rvid' => $rvId, 'flag' => PapersRepository::AVAILABLE_FLAG_VALUES['imported']]])->getQuery()->getSingleScalarResult();
            $values['totalWithoutStartAfterDate']['totalPublished'] = $paperRepo->submissionsQuery(['is' => ['rvid' => $rvId, 'status' => Paper::STATUS_PUBLISHED]])->getQuery()->getSingleScalarResult();
            $values['totalWithoutStartAfterDate']['totalImportedPublished'] = $paperRepo->submissionsQuery(['is' => ['rvid' => $rvId, 'status' => Paper::STATUS_PUBLISHED, 'flag' => PapersRepository::AVAILABLE_FLAG_VALUES['imported']]])->getQuery()->getSingleScalarResult();

        } else {
            $newFilters['is'] = array_merge($filters['is'], ['status' => Paper::STATUS_PUBLISHED]);

            // Imported and published articles
            $importedPublished = $paperRepo->submissionsQuery($newFilters, false, 'publicationDate', false, PapersRepository::AVAILABLE_FLAG_VALUES['imported'])->getQuery()->getSingleScalarResult();
            $values['nbImportedPublished'] = $importedPublished;
        }

        // Imported articles

        try {
            $imported = $paperRepo->submissionsQuery($filters, false, 'submissionDate', false, PapersRepository::AVAILABLE_FLAG_VALUES['imported'])
                ->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $imported = 0;
            $this->logger->error($e->getMessage());
        }
        //Il est possible que certaines versions soient importées tandis que d'autres ne le soient pas.
        // D'où la nécessité d'une nouvelle requête plutôt que de faire la différence

        $submissionsWithoutImported = $paperRepo->getSubmissionsWithoutImported($rvId, $startAfterDate, $years);

        // Only submitted flag : imported articles ignored
        $avgSubmissionsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters);
        $avgPublicationsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_PUBLISHED);
        $medianSubmissionsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_STRICTLY_ACCEPTED, self::MEDIAN_METHOD);
        $medianPublicationsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters, Paper::STATUS_PUBLISHED, self::MEDIAN_METHOD);
        // Published without imported
        $totalPublished = $paperLogRepo->getPublished($rvId, $years, $startAfterDate);
        $totalAccepted = $paperLogRepo->getAccepted($rvId, $years, $startAfterDate); // Tous les articles, même ceux déjà publiés, sont pris en compte pour calculer le taux d'acceptation.
        $totalRefused = $paperLogRepo->getRefused($rvId, $years, $startAfterDate);
        $totalAcceptedNotYetPublished = $paperLogRepo->getAllAcceptedNotYetPublished($rvId, $years, $startAfterDate);
        // articles awaiting an editorial decision, counted directly (by submission date), as "other-status" of the statistics
        $totalOther = $paperRepo->getSubmissionsAwaitingDecision($rvId, $startAfterDate, $years);

        $values[$submissions->getName()] = $nbSubmissions;

        $values['nbImported'] = $imported;
        $values['nbPublished'] = $totalPublished;
        $values['nbRefused'] = $totalRefused;
        $values['nbAccepted'] = $totalAccepted; // To calculate the acceptance rate by review
        $values['nbOtherStatus'] = $totalOther;
        $values['nbAcceptedNotYetPublished'] = $totalAcceptedNotYetPublished;
        $values[$avgSubmissionsDelay->getName()] = $avgSubmissionsDelay->getValue();
        $values[$avgPublicationsDelay->getName()] = $avgPublicationsDelay->getValue();
        $values[$medianSubmissionsDelay->getName()] = $medianSubmissionsDelay->getValue();
        $values[$medianPublicationsDelay->getName()] = $medianPublicationsDelay->getValue();

        if ($years !== []) {

            $values = array_merge($values, ['totalAcceptedSubmittedSameYear' => 0, 'totalPublishedSubmittedSameYear' => 0, 'totalRefusedSubmittedSameYear' => 0]);

            $totalAcceptedSubmittedSameYear = $this->getSubmittedSameYearByYear($rvId, self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, Paper::STATUS_STRICTLY_ACCEPTED);
            $nbPublishedSubmittedSameYear = $this->getSubmittedSameYearByYear($rvId, self::TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR, Paper::STATUS_PUBLISHED);
            $nbRefusedSubmittedSameYear = $this->getSubmittedSameYearByYear($rvId, self::TOTAL_REFUSED_SUBMITTED_SAME_YEAR, Paper::STATUS_REFUSED);

            foreach ($years as $year) {

                if (isset($totalAcceptedSubmittedSameYear[$year])) {
                    $values['totalAcceptedSubmittedSameYear'] += $totalAcceptedSubmittedSameYear[$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR];
                }

                if (isset($nbPublishedSubmittedSameYear[$year])) {
                    $values['totalPublishedSubmittedSameYear'] += $nbPublishedSubmittedSameYear[$year][self::TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR];
                }

                if (isset($nbRefusedSubmittedSameYear[$year])) {
                    $values['totalRefusedSubmittedSameYear'] += $nbRefusedSubmittedSameYear[$year][self::TOTAL_REFUSED_SUBMITTED_SAME_YEAR];
                }
            }

            $values['totalOtherSubmittedSameYear'] = $submissionsWithoutImported - ($values['totalAcceptedSubmittedSameYear'] + $values['totalRefusedSubmittedSameYear']);
        }

        $optionsToRates = [
            'year' => $years,
            'rvid' => $rvId,
            'startAfterDate' => $startAfterDate,
            // Already computed above: avoids recounting them for each rate
            'acceptedTotal' => $totalAccepted,
            'refusedTotal' => $totalRefused,
            'publishedTotal' => $totalPublished,
        ];

        $values['rates'] = [
            'publication' => $paperLogRepo->getPublicationRate($optionsToRates),
            'acceptance' => $paperLogRepo->getAcceptanceRate($optionsToRates),
            'refusal' => $paperLogRepo->getRefusalRate($optionsToRates),
        ];

        if (!isset($filters['is']['submissionDate'])) { // Roles cannot be sorted by year of creation
            $users = $this->getUserStats($filters['is']);
            $values[$users->getName()] = $users->getValue();
        }

        if (isset($filters['is'][AppConstants::WITH_DETAILS])) {

            $details = [
                $submissions->getName() => $submissions->getDetails(),
                $avgSubmissionsDelay->getName() => $avgSubmissionsDelay->getDetails(),
                $avgPublicationsDelay->getName() => $avgPublicationsDelay->getDetails()
            ];

            if (isset($users)) {
                $details[$users->getName()] = $users->getDetails();
            }

            $result->setDetails($details);

        }

        $result->
        setAvailableFilters(ReviewStatsDataProvider::AVAILABLE_FILTERS)->
        setRequestedFilters($context['filters'] ?? [])->
        setName('dashboard')->
        setValue($values);

        return $result;

    }


    /**
     * "withPapers" of the dashboard: the papers (PAPERID) counted by each figure, computed with the same queries as the figures.
     * The papers of the delays are those of the average and of the median delays.
     *
     * @return array<string, list<int>> indexed by figure of the dashboard
     */
    public function getDashboardPaperIds(array $filters): array
    {
        $years = isset($filters['is']['submissionDate']) ? (array)$filters['is']['submissionDate'] : [];
        $rvId = (int)$filters['is']['rvid'];
        $startAfterDate = $filters['is']['startAfterDate'] ?? null;

        /** @var PaperLogRepository $paperLogRepo */
        $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);
        /** @var PapersRepository $paperRepo */
        $paperRepo = $this->entityManager->getRepository(Paper::class);
        $figures = PaperLogRepository::PAPER_IDS_FIGURES;

        $papers = [
            'nbSubmissions' => $paperRepo->getSubmissionPaperIds($filters),
            'nbImported' => $paperRepo->getSubmissionPaperIds($filters, PapersRepository::AVAILABLE_FLAG_VALUES['imported']),
        ];

        if (!($startAfterDate && !$years)) { // as getDashboard()
            $publishedFilters['is'] = array_merge($filters['is'], ['status' => Paper::STATUS_PUBLISHED]);
            $papers['nbImportedPublished'] = $paperRepo->getSubmissionPaperIds($publishedFilters, PapersRepository::AVAILABLE_FLAG_VALUES['imported'], 'publicationDate');
        }

        return $papers + [
            'nbPublished' => $paperLogRepo->getPaperIds($figures['published'], $rvId, $years, $startAfterDate),
            'nbAccepted' => $paperLogRepo->getPaperIds($figures['accepted'], $rvId, $years, $startAfterDate),
            'nbRefused' => $paperLogRepo->getPaperIds($figures['refused'], $rvId, $years, $startAfterDate),
            'nbAcceptedNotYetPublished' => $paperLogRepo->getPaperIds($figures['accepted-not-yet-published'], $rvId, $years, $startAfterDate),
            'nbOtherStatus' => $paperRepo->getSubmissionsAwaitingDecisionPaperIds($rvId, $startAfterDate, $years),
            'submissionAcceptanceTime' => $paperLogRepo->getSubmissionDelayPaperIds($rvId, $years, $startAfterDate, 'median-submission-acceptance', 'day'),
            'submissionPublicationTime' => $paperLogRepo->getSubmissionDelayPaperIds($rvId, $years, $startAfterDate, 'median-submission-publication', 'day'),
        ];
    }

    /**
     * Par annee, par revue, delai moyen, ou la valeur médiane en nombre d'$unité entre dépôt et acceptation|publication
     * @param array $filters
     * @param int $latestStatus
     * @param string $method
     * @param string $unit
     * @return AbstractStatResource
     */

    public function getDelayBetweenSubmissionAndLatestStatus(array $filters = [], int $latestStatus = Paper::STATUS_STRICTLY_ACCEPTED, string $method = self::DEFAULT_METHOD, string $unit = 'day'): AbstractStatResource
    {

        $unit = strtoupper($unit);

        if (!in_array($unit, ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'])) {
            $unit = 'WEEK';
        }

        $year = null;
        $rvId = null;
        $startDate = null;

        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);
        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        if (array_key_exists('submissionDate', $filters['is']) && !empty($filters['is']['submissionDate'])) {
            $year = $filters['is']['submissionDate'];
        }

        if (array_key_exists('rvid', $filters['is']) && !empty($filters['is']['rvid'])) {
            $rvId = (int)$filters['is']['rvid'];
        }

        if (array_key_exists('method', $filters['is']) && in_array($filters['is']['method'], [self::DEFAULT_METHOD, 'median'], true)) {
            $method = $filters['is']['method'];
        }

        if (array_key_exists(self::STATS_UNIT, $filters['is']) && in_array($filters['is'][self::STATS_UNIT], ['day', 'month'], true)) {
            $unit = strtoupper($filters['is'][self::STATS_UNIT]);
        }

        if (isset($filters['is'][AppConstants::START_AFTER_DATE])) {
            $startDate = $filters['is'][AppConstants::START_AFTER_DATE];
        }

        $defaultValue = ['value' => null, self::STATS_UNIT => $unit, self::STATS_METHOD => $method];
        $statResource = $latestStatus === Paper::STATUS_PUBLISHED ? new SubmissionPublicationDelayOutput() : new SubmissionAcceptanceDelayOutput();

        $statResource->setDetails([]);
        $statResource->setAvailableFilters(ReviewStatsDataProvider::AVAILABLE_FILTERS);
        $statResource->setRequestedFilters($filters['is']);

        $statResourceName = 'submission';
        $statResourceName .= $latestStatus === Paper::STATUS_PUBLISHED ? 'Publication' : 'Acceptance';
        $statResourceName .= 'Time';

        /** @var PaperLogRepository $paperLogRepository */
        $paperLogRepository = $this->entityManager->getRepository(PaperLog::class);

        $result = $paperLogRepository->delayBetweenSubmissionAndLatestStatus($unit, $latestStatus, $startDate, $year, $method, $rvId) ?? [];

        if ($method === self::MEDIAN_METHOD) {

            $statResourceName .= ucfirst($method);

            $median = $this->processDelay($result, $method);

            $statResource->setValue(['value' => $median, self::STATS_UNIT => $unit, self::STATS_METHOD => $method]);
            $statResource->setName($statResourceName);

            return $statResource;
        }

        $statResource->setName($statResourceName);


        if ($year && !$rvId) { // all platform by year
            $years = (array)$year;

            $yearRows = array_values(array_filter(
                $result,
                static fn(array $row): bool => in_array($row['year'] ?? null, $years, false)
            ));

            $avg = $yearRows === [] ? null : $this->processDelay($yearRows, $method);

            $statResource->setValue(
                $avg !== null
                    ? ['value' => $avg, self::STATS_UNIT => $unit, self::STATS_METHOD => $method]
                    : $defaultValue
            );


            if ($withDetails) {
                $statResource->setDetails($result);
            }

            return $statResource;
        }

        if (!$year && $rvId) {
            $rvIdResult = $this->applyFilterBy($result, 'rvid', $rvId);

            if (array_key_exists($rvId, $rvIdResult)) {
                $avg = $this->processDelay($rvIdResult[$rvId], $method);
                $statResource->setValue(['value' => $avg, self::STATS_UNIT => $unit, self::STATS_METHOD => $method]);
            }

            if ($withDetails) {

                $reformattedResult = $this->reformatPaperLogData($rvIdResult, $unit, PaperLogRepository::DELAY, $method);

                if (isset($reformattedResult[$rvId])) {
                    $statResource->setDetails($reformattedResult[$rvId]);
                }

            }

            return $statResource;
        }

        if ($year && $rvId) {
            $details = $this->applyFilterBy($result, 'rvid', $rvId);

            $reformattedResult = $this->reformatPaperLogData($details, $unit, PaperLogRepository::DELAY, $method);

            if (isset($reformattedResult[$rvId])) {

                $yearlyValues = [];

                foreach ((array)$year as $y) {
                    if (isset($reformattedResult[$rvId][$y][PaperLogRepository::DELAY]['value'])) {
                        $yearlyValues[] = $reformattedResult[$rvId][$y][PaperLogRepository::DELAY]['value'];
                    }
                }

                if ($yearlyValues !== []) {
                    $statResource->setValue([
                        'value' => $method === self::MEDIAN_METHOD ? $this->getMedian($yearlyValues) : $this->getAvg($yearlyValues),
                        self::STATS_UNIT => $unit,
                        self::STATS_METHOD => $method,
                    ]);

                    if ($withDetails) {
                        $statResource->setDetails($reformattedResult[$rvId]);

                    }

                }

            }

            return $statResource;
        }

        // all platform stats (!year && !rvId)
        $avg = $this->processDelay($result, $method);
        $statResource->setValue(['value' => $avg, self::STATS_UNIT => $unit, self::STATS_METHOD => $method]);

        if ($withDetails) {
            $statResource->setDetails($result);
        }

        return $statResource;


    }


    public function getUserStats(array $filters): UsersStatsOutput
    {
        $rvId = null;

        if (array_key_exists('rvid', $filters)) {
            $rvId = (int)$filters['rvid'];
        }

        $uid = array_key_exists('uid', $filters) ? (int)$filters['uid'] : null;
        $role = (array_key_exists('role', $filters) && !empty($filters['role'])) ? $filters['role'] : null;
        $registrationYear = array_key_exists('registrationDate', $filters) ? (int)$filters['registrationDate'] : null;
        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters);

        $statResource = new UsersStatsOutput();
        $statResource->setAvailableFilters(self::AVAILABLE_USERS_FILTERS);
        $statResource->setRequestedFilters($filters);
        $statResource->setName('nbUsers');

        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);

        $userStats = $userRepository->findByReviewQuery($rvId, $withDetails, $role, $uid, $registrationYear)->getQuery()->getArrayResult();

        try {
            $nbUsers = (int)$userRepository->findByReviewQuery($rvId, false, $role, $uid, $registrationYear)->getQuery()->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            $nbUsers = null;
            $this->logger->error($e->getMessage());
        }

        $details = null;

        if ($withDetails) {

            if ($rvId && !$role) {

                $rvIdResult = $this->applyFilterBy($userStats, 'rvid', (string)$rvId);

                if (array_key_exists($rvId, $rvIdResult)) {
                    $details = $this->reformatUsersData($rvIdResult[$rvId]);
                }

            } elseif (!$rvId && $role) {
                $details = $this->applyFilterBy($userStats, 'role', $role);
            } elseif ($rvId && $role) {

                $rvIdResult = $this->applyFilterBy($userStats, 'rvid', (string)$rvId);
                $reformatted = array_key_exists($rvId, $rvIdResult)
                    ? $this->reformatUsersData($rvIdResult[$rvId])
                    : [];

                $details = array_key_exists($role, $reformatted) ? $reformatted[$role] : [];

            } else {
                // all platform: no rvid, no role
                $reformatted = $this->reformatUsersData($userStats);
                $details = $rvId !== null && array_key_exists($rvId, $reformatted)
                    ? $reformatted[$rvId]
                    : $reformatted;
            }
        }

        $statResource->setValue($nbUsers);
        $statResource->setDetails($details);

        return $statResource;
    }

    /**
     * @param array $filters
     * @param bool $excludeTmpVersions
     * @return SubmissionOutput
     */
    public function getSubmissionsStat(array $filters = [], bool $excludeTmpVersions = false): SubmissionOutput
    {

        $rvId = $filters['is']['rvid'] ?? null;
        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);

        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        $details = [];

        /** @var PapersRepository $papersRepository */
        $papersRepository = $this->entityManager->getRepository(Paper::class);

        try {
            $nbSubmissions = $papersRepository->submissionsQuery($filters)->getQuery()->getSingleScalarResult();

        } catch (NoResultException|NonUniqueResultException $e) {
            $nbSubmissions = null;

            $this->logger->error($e->getMessage());
        }


        if ($withDetails) {

            $navFiltersWithoutYear = $filters;
            unset($navFiltersWithoutYear['is']['submissionDate']);
            $relevantYears = $papersRepository->getSubmissionYearRange($navFiltersWithoutYear);

            $details['years']['statSinceRef'] = self::REF_YEAR;
            $details['years']['relevantYears'] = $relevantYears;

            $this->getSubmissionByYearStats($filters, $rvId, $details);

        }

        return new SubmissionOutput()->
        setAvailableFilters(self::AVAILABLE_PAPERS_FILTERS)->
        setRequestedFilters($filters['is'])->
        setName('nbSubmissions')->
        setValue($nbSubmissions)->
        setDetails($details);
    }


    /**
     * @param array $filters
     * @param mixed $rvId
     * @param array $details
     * @return void
     */
    public function getSubmissionByYearStats(array $filters, mixed $rvId, array &$details = []): void
    {
        /** @var PapersRepository $papersRepository */
        $papersRepository = $this->entityManager->getRepository(Paper::class);
        /** @var PaperLogRepository $paperLogRepository */
        $paperLogRepository = $this->entityManager->getRepository(PaperLog::class);

        $startAfterDate = $filters['is']['startAfterDate'] ?? null;
        $repositories = $papersRepository->getAvailableRepositories($filters);

        $years = $papersRepository->getSubmissionYearRange($filters);

        if ($years === []) {
            return;
        }

        // Values independent of the year, and PAPER_LOG counters for all the years: computed once, outside the loop.
        // The single-year "submissionDate" filter is removed so every year keeps its own imported count,
        // in line with the per-year "submissions" values.
        $importedByYearFilters = $filters;
        unset($importedByYearFilters['is']['submissionDate']);
        $importedByYear = $papersRepository->getImportedByYear($importedByYearFilters);

        $nbAcceptedSubmittedSameYear = $this->getSubmittedSameYearByYear($rvId, self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, Paper::STATUS_STRICTLY_ACCEPTED);

        $acceptedByYear = $paperLogRepository->getAcceptedByYear($rvId, $years, $startAfterDate);
        $refusedByYear = $paperLogRepository->getRefusedByYear($rvId, $years, $startAfterDate);
        $publishedByYear = $paperLogRepository->getPublishedByYear($rvId, $years, $startAfterDate);
        $acceptedNotYetPublishedByYear = $paperLogRepository->getAllAcceptedNotYetPublishedByYear($rvId, $years, $startAfterDate);
        $awaitingDecisionByYear = $papersRepository->getSubmissionsAwaitingDecisionByYear($rvId, $startAfterDate);

        $submissionsByYear = $papersRepository->countSubmissionsByYear(['is' => ['rvid' => $rvId]]);
        $submissionsByYearAndRepo = $papersRepository->countSubmissionsByYearAndRepository([
            'is' => ['rvid' => $rvId, AppConstants::START_AFTER_DATE => $startAfterDate],
        ]);

        foreach ($years as $year) { // pour le dashboard

            $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] = $submissionsByYear[(int)$year] ?? 0;

            $details[self::SUBMISSIONS_BY_YEAR][$year]['imported'] = $importedByYear[(int)$year] ?? 0;

            $accepted = $acceptedByYear[(int)$year] ?? 0;
            $refused = $refusedByYear[(int)$year] ?? 0;
            $published = $publishedByYear[(int)$year] ?? 0;

            $details[self::SUBMISSIONS_BY_YEAR][$year]['published'] = $published;
            $details[self::SUBMISSIONS_BY_YEAR][$year]['acceptedNotYetPublished'] = $acceptedNotYetPublishedByYear[(int)$year] ?? 0;
            $details[self::SUBMISSIONS_BY_YEAR][$year]['refused'] = $refused;
            $details[self::SUBMISSIONS_BY_YEAR][$year]['accepted'] = $accepted;
            $details[self::SUBMISSIONS_BY_YEAR][$year]['others'] = $awaitingDecisionByYear[(int)$year] ?? 0;

            $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] =
                $nbAcceptedSubmittedSameYear[$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] ?? 0;

            $details[self::SUBMISSIONS_BY_YEAR][$year][self::ACCEPTANCE_RATE] = $paperLogRepository->getAcceptanceRate([
                'rvid' => $rvId,
                'year' => [$year],
                'startAfterDate' => $startAfterDate,
                'acceptedTotal' => $accepted, // Avoids another query
                'refusedTotal' => $refused    // idem
            ]);

            foreach ($repositories as $repoId) {
                $details['submissionsByRepo'][$year][$this->metadataSources->getLabel($repoId)]['submissions'] = $submissionsByYearAndRepo[(int)$year][(int)$repoId] ?? 0;
            }
        }

    }

    /**
     * Returns the "submitted same year" counts per year for the given status, keyed by year
     * ([year => [figure => int]]). The shape is consistent whether a journal is selected ($rvId)
     * or the whole platform is queried ($rvId null), so callers can index by [$year] in both cases.
     *
     * @param int|null $rvId     Journal identifier, or null for the whole platform.
     * @param string   $as       Figure key (e.g. self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR).
     * @param int      $status   Paper status to filter on.
     * @return array<int, array<string, int>>
     */
    private function getSubmittedSameYearByYear(int|null $rvId, string $as, int $status): array
    {
        $rows = $this->getNbPapersByStatus($rvId, true, $as, $status);

        if ($rvId) {
            // [$rvId => [year => [$as => int]]]
            return $rows[$rvId] ?? [];
        }

        // Whole platform: $rows is a raw row list, one entry per journal/year.
        // Group and sum by year.
        $byYear = [];

        foreach ($rows as $row) {
            $year = $row['year'] ?? null;

            if ($year === null) {
                continue;
            }

            $byYear[$year][$as] = ($byYear[$year][$as] ?? 0) + (int)($row[$as] ?? 0);
        }

        return $byYear;
    }

    public function getNbPapersByStatus($rvId = null, bool $isSubmittedSameYear = true, $as = self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Paper::STATUS_STRICTLY_ACCEPTED): array
    {
        /** @var PaperLogRepository $paperLogRepository */
        $paperLogRepository = $this->entityManager->getRepository(PaperLog::class);

        try {
            $stmt = $paperLogRepository->totalNbPapersByStatusStatement($isSubmittedSameYear, $as, $status);

            if ($stmt) {

                $result = $stmt->executeQuery()->fetchAllAssociative();

                if ($rvId) {

                    //before reformat data : [ 8 => [0 => ["year" => 2023, PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [], ... ]
                    $filtered = $this->applyFilterBy($result, 'rvid', $rvId);

                    //after: [8 => [ 2023 => [PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [2022 => ], .....
                    // ['extractedKey' => []] if empty result

                    if (!empty($filtered[$rvId])) {
                        return $this->reformatPaperLogData($filtered, null, $as);
                    }
                    return $filtered;

                }

                return $result;
            }

        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return [];


    }


    /**
     * @param array $context //['code' => $rvCode]
     * @return Review|null
     */
    public function getJournal(array $context): ?Review
    {
        /** @var ReviewRepository $journalRepository */
        $journalRepository = $this->entityManager->getRepository(Review::class);
        return $journalRepository->getJournalByIdentifier($context['code']);
    }

    /**
     * @param array $array
     * @param string $method
     * @param string $key
     * @return int|float|null
     */
    private function processDelay(array $array, string $method = self::DEFAULT_METHOD, string $key = PaperLogRepository::DELAY): int|float|null
    {

        $values = array_column($array, $key);
        $validValues = array_filter($values, is_numeric(...));

        if ($method !== self::MEDIAN_METHOD) {
            return $this->getAvg($validValues);
        }

        return $this->getMedian($validValues);

    }

    private function reformatUsersData(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            $rvId = $value['rvid'] ?? null;
            $role = $value['role'] ?? null;
            $nbUsers = $value['nbUsers'] ?? 0;

            if ($rvId === null && $role !== null) {
                $result[$role]['nbUsers'] = $nbUsers;
            } elseif ($role !== null) {
                $result[$rvId][$role]['nbUsers'] = $nbUsers;
            }
        }

        return $result;
    }


    private function reformatPaperLogData(array $array, ?string $unit = null, string $extractedField = PaperLogRepository::DELAY, string $method = self::DEFAULT_METHOD): array
    {

        $result = [];

        foreach ($array as $rvId => $value) {
            $year = null;

            foreach ($value as $v) {

                if (!is_array($v)) {
                    continue;
                }

                foreach ($v as $kv => $vv) {

                    if ($kv === 'year') {
                        $year = $vv;
                    }

                    if ($kv === $extractedField) {
                        $result[$rvId][$year][$kv] = in_array($extractedField, [self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, self::TOTAL_REFUSED_SUBMITTED_SAME_YEAR, self::TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR], true) ? (int)$vv : ['value' => (float)$vv, self::STATS_UNIT => $unit, self::STATS_METHOD => $method];
                    }
                }

            }
        }


        return $result;
    }
}
