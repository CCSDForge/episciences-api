<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\PaperLog;
use App\Entity\Papers;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PapersRepository;
use App\Resource\AbstractStatResource;
use App\Resource\DashboardOutput;
use App\Traits\CheckExistingResourceTrait;
use App\Traits\ToolsTrait;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractDataProvider
{
    use CheckExistingResourceTrait;
    use ToolsTrait;

    private EntityManagerInterface $entityManagerInterface;


    public function __construct(EntityManagerInterface $entityManagerInterface)
    {
        $this->entityManagerInterface = $entityManagerInterface;
    }


    abstract protected function supports(Operation $operation = null): bool;

    protected function getCollection(Operation $operation, array $context = []): array|AbstractStatResource|null
    {

        $result = null;
        $cFilters = $context['filters'] ?? [];

        if (isset($context['uri_variables'])) { // exp. {code} = rvcode

            $filters['is'] = $context['uri_variables']; // available filters

            $journal = $this->
            entityManagerInterface->
            getRepository(Review::class)->findOneBy($context['uri_variables']);

            if (!$journal) {
                return null;
            }


            $filters['is']['rvid'] = (string)$journal->getRvid();

            $this->addFilters($filters['is'], $operation->getName(), $cFilters);

            if ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][1]) {
                $result = $this->entityManagerInterface->
                getRepository(Papers::class)->
                getSubmissionsStat($filters);

            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][2]) {
                $result = $this->entityManagerInterface->
                getRepository(PaperLog::class)->getDelayBetweenSubmissionAndLatestStatus($filters);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][3]) {
                $result = $this->entityManagerInterface->
                getRepository(PaperLog::class)->
                getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][0]) {
                $result = $this->getDashboard($context, $filters);
            } elseif ($operation->getName() === AppConstants::APP_CONST['custom_operations']['items']['review'][4]) {
                $result = $this->entityManagerInterface->
                getRepository(User::class)->
                getUserStats($filters['is']);
            }
        }

        if ($result instanceof AbstractStatResource) {
            $result->setRequestedFilters($cFilters);
        }

        return $result;

    }


    private function getDashboard($context, $filters): DashboardOutput
    {

        $result = new DashboardOutput();

        $papersRepo = $this->entityManagerInterface->
        getRepository(Papers::class);

        $submissions = $papersRepo->getSubmissionsStat($filters);
        $submissionsDelay = $this->entityManagerInterface->
        getRepository(PaperLog::class)->
        getDelayBetweenSubmissionAndLatestStatus($filters);
        $publicationsDelay = $this->entityManagerInterface->
        getRepository(PaperLog::class)->
        getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);


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

        if (!isset($filters['is']['year'])) {
            $users = $this->entityManagerInterface->getRepository(User::class)->getUserStats($filters['is']);
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
                    in_array($filter, PapersRepository::AVAILABLE_FILTERS, true)
                ) {
                    $availableFilters[$filter] = $value;
                }

            }

        }
    }

}

