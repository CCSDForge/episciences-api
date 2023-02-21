<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\AppConstants;
use App\Entity\Main\PaperLog;
use App\Entity\Main\Papers;
use App\Entity\Main\Review;
use App\Entity\Main\User;
use App\Resource\AbstractStatResource;
use App\Resource\DashboardOutput;
use App\Resource\ToBeDeletedStatResource;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractDataProvider
{
    use CheckExistingResourceTrait;

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

        if (isset($context['uri_variables'])) {

            $filters['is'] = $context['uri_variables'];
            $withDetails = isset($context['filters']) &&
                array_key_exists(AppConstants::WITH_DETAILS, $context['filters']);

            if ($withDetails) {
                $filters['is'][AppConstants::WITH_DETAILS] = true;
            } else {
                $context['filters'] = [];
            }

            $journal = $this->
            entityManagerInterface->
            getRepository(Review::class)->findOneBy($context['uri_variables']);

            if (!$journal) {
                return null;
            }


            $filters['is']['rvid'] = (string)$journal->getRvid();

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
                $result = $this->getDashboard($context, $filters, $withDetails);
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


    private function getDashboard($context, $filters, $withDetails): DashboardOutput
    {

        $result = new DashboardOutput();

        $submissions = $this->entityManagerInterface->
        getRepository(Papers::class)->
        getSubmissionsStat($filters);
        $submissionsDelay = $this->entityManagerInterface->
        getRepository(PaperLog::class)->
        getDelayBetweenSubmissionAndLatestStatus($filters);
        $publicationsDelay = $this->entityManagerInterface->
        getRepository(PaperLog::class)->
        getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);

        // aggregate stats
        $values = [
            $submissions->getName() => $submissions->getValue(),
            $submissionsDelay->getName() => $submissionsDelay->getValue(),
            $publicationsDelay->getName() => $publicationsDelay->getValue()
        ];

        if (!isset($filters['is']['year'])) {
            $users = $this->entityManagerInterface->getRepository(User::class)->getUserStats($filters['is']);
            $values[$users->getName()] = $users->getValue();
        }

        if ($withDetails) {

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
        setRequestedFilters($context['filters'])->
        setName('dashboard')->
        setValue($values);

        return $result;

    }


}

