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
    public const AVAILABLE_PAPERS_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::PAPER_FLAG, AppConstants::PAPER_STATUS, AppConstants::PAPER_STATUS, AppConstants::YEAR_PARAM];
    public const AVAILABLE_USER_ROLES_FILTERS = ['rvid', 'uid', 'role'];
    public const AVAILABLE_USERS_FILTERS = [AppConstants::WITH_DETAILS];
    public const DEFAULT_METHOD = 'average';
    public const MEDIAN_METHOD = 'median';

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly MetadataSources $metadataSources, private readonly LoggerInterface $logger)
    {

    }

    public function getDashboard($context, $filters): DashboardOutput
    {

        $years = isset($filters['is']['submissionDate']) ? (array)$filters['is']['submissionDate'] : [];
        $rvId = $filters['is']['rvid'] ?? null;
        $startAfterDate = $filters['is']['startAfterDate'] ?? null;

        $result = new DashboardOutput();

        $paperLogRepo = $this->entityManager->getRepository(PaperLog::class);

        $submissions = $this->getSubmissionsStat($filters);
        $nbSubmissions = $submissions->getValue(); // imported submissions included

        $paperRepo = $this->entityManager->getRepository(Paper::class);


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
            $values ['nbImportedPublished'] = $importedPublished;
        }

        // Imported articles

        $imported = $paperRepo->submissionsQuery($filters, false, 'submissionDate', false, PapersRepository::AVAILABLE_FLAG_VALUES['imported'])
            ->getQuery()->getSingleScalarResult();
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
        $totalOther = max(0, $submissionsWithoutImported - ($totalAccepted + $totalRefused));

        $values [$submissions->getName()] = $nbSubmissions;

        $values ['nbImported'] = $imported;
        $values ['nbPublished'] = $totalPublished;
        $values['nbRefused'] = $totalRefused;
        $values['nbAccepted'] = $totalAccepted; //  To calculate the acceptance rate by review
        $values['nbOtherStatus'] = $totalOther;
        $values ['nbAcceptedNotYetPublished'] = $totalAcceptedNotYetPublished;
        $values[$avgSubmissionsDelay->getName()] = $avgSubmissionsDelay->getValue();
        $values[$avgPublicationsDelay->getName()] = $avgPublicationsDelay->getValue();
        $values[$medianSubmissionsDelay->getName()] = $medianSubmissionsDelay->getValue();
        $values[$medianPublicationsDelay->getName()] = $medianPublicationsDelay->getValue();


        if ($years) {

            $values = array_merge($values, ['totalAcceptedSubmittedSameYear' => 0, 'totalPublishedSubmittedSameYear' => 0, 'totalRefusedSubmittedSameYear' => 0]);

            $totalAcceptedSubmittedSameYear = $this->getNbPapersByStatus($rvId)[$rvId];
            $nbPublishedSubmittedSameYear = $this->getNbPapersByStatus($rvId, true, self::TOTAL_PUBLISHED_SUBMITTED_SAME_YEAR, Paper::STATUS_PUBLISHED)[$rvId];
            $nbRefusedSubmittedSameYear = $this->getNbPapersByStatus($rvId, true, self::TOTAL_REFUSED_SUBMITTED_SAME_YEAR, Paper::STATUS_REFUSED)[$rvId];

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


            $values ['rate'] = $this->getPercentages(['totalSubmissions' => $submissionsWithoutImported, 'totalAccepted' => $values['totalAcceptedSubmittedSameYear'], 'totalPublished' => $values['totalPublishedSubmittedSameYear'], 'totalRefused' => $values['totalRefusedSubmittedSameYear']]);


        } else {
            $values ['rate'] = $this->getPercentages(['totalSubmissions' => $submissionsWithoutImported, 'totalAccepted' => $totalAccepted, 'totalPublished' => $totalPublished, 'totalRefused' => $totalRefused]);

        }


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
            $yearResult = $this->applyFilterBy($result, 'year', $year);

            $avg = array_key_exists($year, $yearResult) ? $this->processDelay($yearResult[$year], $method) : null;

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

                $statResource->setValue($reformattedResult[$rvId][$year][PaperLogRepository::DELAY]);

                if ($withDetails) {
                    $statResource->setDetails($reformattedResult[$rvId]);

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
                    $statResource->setDetails($details);
                }

            } elseif (!$rvId && $role) {
                $roleResult = $this->applyFilterBy($userStats, 'role', $role);
                $statResource->setDetails($roleResult);
            } elseif ($rvId && $role) {

                $details = $this->applyFilterBy($userStats, 'rvid', (string)$rvId);

                if (array_key_exists($rvId, $details)) {
                    $details = $this->reformatUsersData($details[$rvId]);
                }

                $roleResult = array_key_exists($role, $details) ? $details[$role] : [];
                $statResource->setDetails($roleResult);

            }

            $details = array_key_exists($rvId, $this->reformatUsersData($userStats)) ?
                $this->reformatUsersData($userStats)[$rvId] :
                $this->reformatUsersData($userStats);
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

        return (new SubmissionOutput())->
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
        $papersRepository = $this->entityManager->getRepository(Paper::class);
        $startAfterDate = $filters['is']['startAfterDate'] ?? null;
        $repositories = $papersRepository->getAvailableRepositories($filters);

        foreach ($papersRepository->getSubmissionYearRange($filters) as $year) { // pour le dashboard
            try {
                $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] = $papersRepository->
                submissionsQuery(['is' => ['rvid' => $rvId, 'submissionDate' => $year]])->
                getQuery()->
                getSingleScalarResult();

                $details[self::SUBMISSIONS_BY_YEAR][$year]['imported'] = $this->entityManager->getRepository(Paper::class)->submissionsQuery(['is' => ['rvid' => $rvId, 'submissionDate' => $year]], false, 'submissionDate', false, PapersRepository::AVAILABLE_FLAG_VALUES['imported'])->getQuery()->getSingleScalarResult();

                $details[self::SUBMISSIONS_BY_YEAR][$year]['published'] = $this->entityManager->getRepository(PaperLog::class)->getPublished($rvId, [$year], $startAfterDate);
                $details[self::SUBMISSIONS_BY_YEAR][$year]['acceptedNotYetPublished'] = $this->entityManager->getRepository(PaperLog::class)->getAllAcceptedNotYetPublished($rvId, [$year], $startAfterDate);
                $details[self::SUBMISSIONS_BY_YEAR][$year]['refused'] = $this->entityManager->getRepository(PaperLog::class)->getRefused($rvId, [$year], $startAfterDate);
                $details[self::SUBMISSIONS_BY_YEAR][$year]['accepted'] = $this->entityManager->getRepository(PaperLog::class)->getAccepted($rvId, [$year], $startAfterDate);
                $details[self::SUBMISSIONS_BY_YEAR][$year]['others'] = max(0, $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] - ($details[self::SUBMISSIONS_BY_YEAR][$year]['published'] + $details[self::SUBMISSIONS_BY_YEAR][$year]['acceptedNotYetPublished'] + $details[self::SUBMISSIONS_BY_YEAR][$year]['refused']));


                $nbAcceptedSubmittedSameYear = $this->getNbPapersByStatus($rvId);


                $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] =
                    isset($nbAcceptedSubmittedSameYear[$rvId][$year]) ?
                        $nbAcceptedSubmittedSameYear[$rvId][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] :
                        0;

                $details[self::SUBMISSIONS_BY_YEAR][$year][self::ACCEPTANCE_RATE] = $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] ?
                    round($details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] / $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] * 100, 2, PHP_ROUND_HALF_UP) : 0;


                foreach ($repositories as $repoId) {
                    $details['submissionsByRepo'][$year][$this->metadataSources->getLabel($repoId)]['submissions'] = $papersRepository->
                    submissionsQuery(
                        ['is' => ['rvid' => $rvId, AppConstants::SUBMISSION_DATE => $year, 'repoid' => $repoId, AppConstants::START_AFTER_DATE => $startAfterDate]
                        ])->getQuery()->getSingleScalarResult();
                }
            } catch (NoResultException|NonUniqueResultException $e) {
                $this->logger->error($e->getMessage());
            }
        }

    }


    public function getNbPapersByStatus($rvId = null, bool $isSubmittedSameYear = true, $as = self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Paper::STATUS_STRICTLY_ACCEPTED): array
    {

        try {
            $stmt = $this->entityManager->getRepository(PaperLog::class)->totalNbPapersByStatusStatement($isSubmittedSameYear, $as, $status);

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

                return $stmt->executeQuery()->fetchAllAssociative();
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
        return $this->entityManager->getRepository(Review::class)->getJournalByIdentifier($context['code']);
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
        $validValues = array_filter($values, static function ($value) {
            return is_numeric($value);
        });

        if ($method !== self::MEDIAN_METHOD) {
            return $this->getAvg($validValues);
        }

        try {
            $median = $this->getMedian($validValues);

        } catch (\LengthException $e) {
            $this->logger->critical($e->getMessage());
            $median = null;
        }

        return $median;

    }

    private function reformatSubmissionsData(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            $rvId = $value['rvid'] ?? null;
            $year = $value['year'] ?? null;
            $repoId = $value['repoid'] ?? null;
            $status = $value['status'] ?? null;
            $nbSubmissions = $value['nbSubmissions'] ?? 0;

            if ($rvId === null) {
                $result[$year][$this->metadataSources->getLabel($repoId)][Paper::STATUS_DICTIONARY[$status]]['nbSubmissions'] = $nbSubmissions;
            } else {
                $result[$rvId][$year][$this->metadataSources->getLabel($repoId)][Paper::STATUS_DICTIONARY[$status]]['nbSubmissions'] = $nbSubmissions;
            }
        }

        return $result;
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


    private function reformatPaperLogData(array $array, string $unit = null, string $extractedField = PaperLogRepository::DELAY, string $method = self::DEFAULT_METHOD): array
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

    private function getPercentages($data = ['totalSubmissions' => 0, 'totalAccepted' => 0, 'totalPublished' => 0, 'totalRefused' => 0]): array
    {

        if (!isset($data['totalSubmissions']) || $data['totalSubmissions'] < 0) {
            return ['published' => 0, 'accepted' => 0, 'refused' => 0, 'other' => 0];
        }

        $publishedPercentage = $data['totalPublished'] ? round($data['totalPublished'] / $data['totalSubmissions'] * 100, AppConstants::RATE_DEFAULT_PRECISION, PHP_ROUND_HALF_UP) : 0;
        $acceptedPercentage = $data['totalAccepted'] ? round($data['totalAccepted'] / $data['totalSubmissions'] * 100, AppConstants::RATE_DEFAULT_PRECISION, PHP_ROUND_HALF_UP) : 0;
        $refusedPercentage = $data['totalRefused'] ? round($data['totalRefused'] / $data['totalSubmissions'] * 100, AppConstants::RATE_DEFAULT_PRECISION, PHP_ROUND_HALF_UP) : 0;
        $otherPercentage = round(100 - ($acceptedPercentage + $refusedPercentage), AppConstants::RATE_DEFAULT_PRECISION, PHP_ROUND_HALF_UP);

        return [
            'published' => $publishedPercentage,
            'accepted' => $acceptedPercentage,
            'refused' => $refusedPercentage,
            'other' => $otherPercentage
        ];

    }
}
