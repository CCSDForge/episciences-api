<?php

declare(strict_types=1);

namespace App\DataProvider;


use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Main\PaperLog;
use App\Entity\Main\Papers;
use App\Entity\Main\Review;
use App\Entity\Main\User;
use App\Repository\Main\PaperLogRepository;
use App\Repository\Main\PapersRepository;
use App\Repository\Main\UserRepository;
use App\Resource\StatResource;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;

final class ReviewStatsDataProvider implements StatsProviderInterface, ProviderInterface
{

    public const AVAILABLE_FILTERS = ['rvid', 'submissionDate', 'method', 'unit', 'withDetails', 'uid', 'role', 'repoid', 'status'];

    use CheckExistingResourceTrait;

    private EntityManagerInterface $entityManagerInterface;

    public function __construct(EntityManagerInterface $entityManagerInterface)
    {
        $this->entityManagerInterface = $entityManagerInterface;
    }

    public function supports(Operation $operation = null): bool
    {
        return (
            $operation &&
            (Review::class === $operation->getClass()) &&
            in_array($operation->getName(), AppConstants::APP_CONST['custom_operations'], true)
        );
    }

    public function getCollection(Operation $operation, array $context = []): array | StatResource
    {
        $papersRepo = $this->entityManagerInterface->getRepository(Papers::class);
        $paperLogRepo = $this->entityManagerInterface->getRepository(PaperLog::class);
        $filters['is'] = $context['filters'] ?? [];
        $filters['is'] = array_merge($context['uri_variables'], $filters['is']);
        $withDetails = array_key_exists('withDetails', $filters['is']);
        $filters['is']['withDetails'] = $withDetails;

        if (isset($context['uri_variables'])) {
            $journal = $this->
            entityManagerInterface->
            getRepository(Review::class)->findOneBy($context['uri_variables']);

            if ($journal) {
                $filters['is']['rvid'] = (string)$journal->getRvid();
            }

        }

        $dashboard = new StatResource();

        if ($operation->getName() === AppConstants::APP_CONST['custom_operations'][0]) {

            $submissions = $papersRepo->getSubmissionsStat($filters);
            $submissionsDelay = $paperLogRepo->getDelayBetweenSubmissionAndLatestStatus($filters);
            $publicationsDelay = $paperLogRepo->getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);

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

                $dashboard->setDetails($details);

            }

            $dashboard->
            setId($context['uri_variables']['code'])->
            setAvailableFilters(self::AVAILABLE_FILTERS)->
            setRequestedFilters($filters['is'])->
            setName('dashboard')->
            setValue($values);
        }

        return $dashboard;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->getCollection($operation, $context);


    }
}
