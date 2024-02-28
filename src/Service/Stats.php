<?php

namespace App\Service;

use App\AppConstants;
use App\DataProvider\ReviewStatsDataProvider;
use App\Entity\PaperLog;
use App\Entity\Papers;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PaperLogRepository;
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
    public const ACCEPTANCE_RATE = 'acceptanceRate'; // TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR / submissions by year
    public const MORE_DETAILS = 'moreDetailsFromModifDate';
    public const REF_YEAR = 2013; // statistics since this year
    public const AVAILABLE_PAPERS_FILTERS = [AppConstants::WITH_DETAILS, AppConstants::PAPER_FLAG, AppConstants::PAPER_STATUS, AppConstants::PAPER_STATUS, AppConstants::YEAR_PARAM];
    public const AVAILABLE_USER_ROLES_FILTERS = ['rvid', 'uid', 'role'];
    public const AVAILABLE_USERS_FILTERS = [AppConstants::WITH_DETAILS];

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly MetadataSources $metadataSources, private readonly LoggerInterface $logger)
    {

    }

    public function getDashboard($context, $filters): DashboardOutput
    {

        $result = new DashboardOutput();

        $papersRepo = $this->entityManager->getRepository(Papers::class);

        $submissions = $this->getSubmissionsStat($filters);
        $submissionsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters);
        $publicationsDelay = $this->getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);


        $totalPublished = $papersRepo
            ->submissionsQuery([
                'is' => array_merge($filters['is'], ['status' => Papers::STATUS_PUBLISHED])
            ])
            ->getQuery()
            ->getSingleScalarResult();


        // aggregate stats
        $values = [
            $submissions->getName() => $submissions->getValue(),
            'totalPublished' => $totalPublished,
            $submissionsDelay->getName() => $submissionsDelay->getValue(),
            $publicationsDelay->getName() => $publicationsDelay->getValue()
        ];

        if (!isset($filters['is']['submissionDate'])) { // Roles cannot be sorted by year of creation
            $users = $this->getUserStats($filters['is']);
            $values[$users->getName()] = $users->getValue();
        }

        if (isset($filters['is'][AppConstants::WITH_DETAILS])) {

            $details = [
                $submissions->getName() => $submissions->getDetails(),
                $submissionsDelay->getName() => $submissionsDelay->getDetails(),
                $publicationsDelay->getName() => $publicationsDelay->getDetails()
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
     * Par annee, par revue, delai moyen, ou la valeur médiane en nombre de jours (ou mois) entre dépôt et acceptation
     * @param array $filters
     * @param int $latestStatus
     * @return AbstractStatResource
     */

    public function getDelayBetweenSubmissionAndLatestStatus(array $filters = [], int $latestStatus = Papers::STATUS_ACCEPTED): AbstractStatResource
    {

        $year = null;
        $rvId = null;
        $method = 'average';
        $unit = 'DAY';
        $startDate = null;

        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);
        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        if (array_key_exists('submissionDate', $filters['is']) && !empty($filters['is']['submissionDate'])) {
            $year = $filters['is']['submissionDate'];
        }

        if (array_key_exists('rvid', $filters['is']) && !empty($filters['is']['rvid'])) {
            $rvId = $filters['is']['rvid'];
        }

        if (array_key_exists('method', $filters['is']) && in_array($filters['is']['method'], ['average', 'median'], true)) {
            $method = $filters['is']['method'];
        }

        if (array_key_exists(self::STATS_UNIT, $filters['is']) && in_array($filters['is'][self::STATS_UNIT], ['day', 'month'], true)) {
            $unit = strtoupper($filters['is'][self::STATS_UNIT]);
        }

        if (isset($filters['is'][AppConstants::START_AFTER_DATE])) {
            $startDate = $filters['is'][AppConstants::START_AFTER_DATE];
        }

        $statResource = $latestStatus === Papers::STATUS_PUBLISHED ? new SubmissionPublicationDelayOutput() : new SubmissionAcceptanceDelayOutput();

        $statResource->setDetails([]);
        $statResource->setAvailableFilters(ReviewStatsDataProvider::AVAILABLE_FILTERS);
        $statResource->setRequestedFilters($filters['is']);
        $statResourceName = 'submission';
        $statResourceName .= $latestStatus === Papers::STATUS_PUBLISHED ? 'Publication' : 'Acceptance';
        $statResourceName .= 'Time';
        $statResource->setName($statResourceName);

        $paperLogRepository = $this->entityManager->getRepository(PaperLog::class);
        $result = $paperLogRepository->delayBetweenSubmissionAndLatestStatus($unit, $latestStatus, $startDate, $year);

        if ($year && !$rvId) { // all platform by year
            $yearResult = $this->applyFilterBy($result, 'year', $year);
            if (array_key_exists($year, $yearResult)) {
                $statResource->setValue($this->avg($yearResult[$year]));
            } else {
                $statResource->setValue(null);
            }

            if ($withDetails) {
                $statResource->setDetails($result);
            }

            return $statResource;
        }

        if (!$year && $rvId) {
            $rvIdResult = $this->applyFilterBy($result, 'rvid', $rvId);
            if (array_key_exists($rvId, $rvIdResult)) {
                $statResource->setValue(['value' => $this->avg($rvIdResult[$rvId]), self::STATS_UNIT => $unit, self::STATS_METHOD => $method]);
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

            $reformattedResult = $this->reformatPaperLogData($details, $unit,PaperLogRepository::DELAY, $method);

            if (isset($reformattedResult[$rvId])) {

                $statResource->setValue($reformattedResult[$rvId][$year][PaperLogRepository::DELAY]);

                if ($withDetails) {
                    $statResource->setDetails($reformattedResult[$rvId]);

                }

            }

            return $statResource;
        }

        // all platform stats (!year && !rvId)
        $statResource->setValue($this->avg($result));

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

        $userStats = $userRepository->findByReviewQuery($rvId, $uid, $role, $withDetails, $registrationYear)->getQuery()->getArrayResult();

        try {
            $nbUsers = (int)$userRepository->findByReviewQuery($rvId, $uid, $role, false, $registrationYear)->getQuery()->getSingleScalarResult();
        } catch (NoResultException | NonUniqueResultException $e) {
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
                $this->reformatUsersData($userStats) ;
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
        $status = $filters['is']['status'] ?? null; // submitted: $status = 0
        $year = (array_key_exists('submissionDate', $filters['is']) && !empty($filters['is']['submissionDate'])) ?
            $filters['is']['submissionDate'] : null;
        $repoId = $filters['is']['repoid'] ?? null; // tmp version: $repoId = 0
        $withDetails = array_key_exists(AppConstants::WITH_DETAILS, $filters['is']);

        $filters['is'][AppConstants::WITH_DETAILS] = $withDetails;

        $details = [];

        $papersRepository = $this->entityManager->getRepository(Papers::class);

        try {
            $nbSubmissions = (int)$papersRepository->submissionsQuery($filters)->getQuery()->getSingleScalarResult();

        } catch (NoResultException|NonUniqueResultException $e) {
            $nbSubmissions = null;

            $this->logger->error($e->getMessage());
        }


        if ($withDetails) {

            $submissionsStats = $papersRepository->flexibleSubmissionsQueryDetails($filters, $excludeTmpVersions)->
            getQuery()->getArrayResult();

            if (!empty($submissionsStats)) {

                if (null !== $rvId && null === $status && null === $repoId) { // by review
                    $rvIdResult = $this->applyFilterBy($submissionsStats, 'rvid', $rvId);
                    $details[self::MORE_DETAILS] = $this->reformatSubmissionsData($rvIdResult[$rvId]);


                } elseif (null === $rvId && $year && null === $status && $repoId === null) { // by year
                    $yearResult = $this->applyFilterBy($submissionsStats, 'year', $year);
                    $details[self::MORE_DETAILS] = $yearResult;


                } elseif (null === $rvId && !$year && null === $status && $repoId !== null) { // by $repoId
                    $repoResult = $this->applyFilterBy($submissionsStats, 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $repoResult;


                } elseif (null === $rvId && !$year && null !== $status && $repoId === null) { // by $status
                    $statusResult = $this->applyFilterBy($submissionsStats, 'status', (string)$status);
                    $details[self::MORE_DETAILS] = $statusResult;


                } elseif ($year && $rvId && null === $status && $repoId === null) { // $review & $year
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);



                } elseif (!$year && $rvId && null === $status && $repoId !== null) { // $review & $repoId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'repoid', (string)$repoId);


                } elseif (!$year && $rvId && null !== $status && $repoId === null) { // $review & $status
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($rvIdDetails[$rvId], 'status', (string)$status);


                } elseif ($year && $rvId && null === $status && $repoId !== null) { // $review & $year & repoId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);


                } elseif ($year && $rvId && null !== $status && $repoId === null) { // $review & $year & $status
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'status', (string)$status);


                } elseif ($year && !$rvId && null !== $status && $repoId === null) { //$year & $status
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'status', (string)$status);


                } elseif ($year && !$rvId && null === $status && $repoId !== null) { //$year & $repoId
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);


                } elseif (!$year && !$rvId && null !== $status && $repoId !== null) { //$repoId & $status
                    $repoDetails = $this->applyFilterBy($submissionsStats, 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif (!$year && $rvId && null !== $status && $repoId !== null) { //$repoId & $status & $rvId
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $repoDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif ($year && !$rvId && null !== $status && $repoId !== null) { // $year && $repoId && $status
                    $yearDetails = $this->applyFilterBy($submissionsStats, 'year', (string)$year);
                    $repoDetails = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } elseif ($year && $rvId && null !== $status && $repoId !== null) { // $year && $rvId && $repoId & $year
                    $rvIdDetails = $this->applyFilterBy($submissionsStats, 'rvid', (string)$rvId);
                    $yearDetails = $this->applyFilterBy($rvIdDetails[$rvId], 'year', (string)$year);
                    $repoDetails = $this->applyFilterBy($yearDetails[$year], 'repoid', (string)$repoId);
                    $details[self::MORE_DETAILS] = $this->applyFilterBy($repoDetails[$repoId], 'status', (string)$status);


                } else {
                    $details[self::MORE_DETAILS] = $this->reformatSubmissionsData($submissionsStats);
                }

            }

            $navFiltersWithoutYear = $filters;
            unset($navFiltersWithoutYear['is']['submissionDate']);
            $relevantYears= $papersRepository->getAvailableSubmissionYears($navFiltersWithoutYear);

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
        $papersRepository = $this->entityManager->getRepository(Papers::class);
        $startAfterDate = $filters['is']['startAfterDate'] ?? null;
        $repositories = $papersRepository->getAvailableRepositories($filters);

        foreach ($papersRepository->getAvailableSubmissionYears($filters) as $year) { // pour le dashboard
            try {
                $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] = $papersRepository->
                submissionsQuery(['is' => ['rvid' => $rvId, 'submissionDate' => $year]])->
                getQuery()->
                getSingleScalarResult();

                $details[self::SUBMISSIONS_BY_YEAR][$year]['publications'] = $papersRepository->
                submissionsQuery(
                    [
                        'is' => [
                            'rvid' => $rvId,
                            'status' => Papers::STATUS_PUBLISHED,
                            AppConstants::SUBMISSION_DATE => $year,
                            AppConstants::START_AFTER_DATE
                        ]
                    ], false, 'publicationDate'
                )->
                getQuery()->getSingleScalarResult();

                $totalNumberOfPapersAcceptedSubmittedSameYear = $this->getTotalNumberOfPapersByStatus($rvId);

                $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] =
                    isset($totalNumberOfPapersAcceptedSubmittedSameYear[$rvId][$year]) ?
                        $totalNumberOfPapersAcceptedSubmittedSameYear[$rvId][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] :
                        0;

                $details[self::SUBMISSIONS_BY_YEAR][$year][self::ACCEPTANCE_RATE] = $details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] ?
                    round($details[self::SUBMISSIONS_BY_YEAR][$year][self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR] / $details[self::SUBMISSIONS_BY_YEAR][$year]['submissions'] * 100, 2) : 0;


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


    public function getTotalNumberOfPapersByStatus($rvId = null, bool $isSubmittedSameYear = true, $as = self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR, int $status = Papers::STATUS_ACCEPTED): array
    {

        try {
            $stmt = $this->entityManager->getRepository(PaperLog::class)->totalNumberOfPapersByStatus($isSubmittedSameYear, $as, $status);

            if ($stmt && $rvId) {

                //before reformat data : [ 8 => [0 => ["year" => 2023, PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [], ... ]
                return $this->reformatPaperLogData(
                    $this->applyFilterBy($stmt->executeQuery()->fetchAllAssociative(), 'rvid', $rvId),
                    null, $as, 'average'
                );

                //after: [8 => [ 2023 => [PapersRepository::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR => 18], [2022 => ], .....


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
        return $this->entityManager->getRepository(Review::class)->findOneBy($context);
    }

    /**
     * @param array $array
     * @param string $key
     * @return float | null
     */
    private function avg(array $array, string $key = PaperLogRepository::DELAY): ?float
    {
        if (empty($array)) {
            return null;
        }

        $total = array_sum(array_column($array, $key));
        $count = count($array);

        return round($total / $count, 2);
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
                $result[$year][$this->metadataSources->getLabel($repoId)][Papers::STATUS_DICTIONARY[$status]]['nbSubmissions'] = $nbSubmissions;
            } else {
                $result[$rvId][$year][$this->metadataSources->getLabel($repoId)][Papers::STATUS_DICTIONARY[$status]]['nbSubmissions'] = $nbSubmissions;
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


    private function reformatPaperLogData(array $array, string $unit = null, string $extractedField = PaperLogRepository::DELAY, string $method = 'average'): array
    {

        $result = [];

        foreach ($array as $rvId => $value) {
            $year = null;

            foreach ($value as $v) {

                foreach ($v as $kv => $vv) {

                    if ($kv === 'year') {
                        $year = $vv;
                    }

                    if ($kv === $extractedField) {
                        $result[$rvId][$year][$kv] = $extractedField === self::TOTAL_ACCEPTED_SUBMITTED_SAME_YEAR ? (int)$vv : ['value' => (float)$vv, self::STATS_UNIT => $unit, self::STATS_METHOD => $method];
                    }
                }

            }
        }


        return $result;
    }
}