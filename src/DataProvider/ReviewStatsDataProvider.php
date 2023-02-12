<?php

declare(strict_types=1);

namespace App\DataProvider;


use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Main\PaperLog;
use App\Entity\Main\Papers;
use App\Entity\Main\Review;
use App\Entity\Main\User;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;

final class ReviewStatsDataProvider implements ProviderInterface
{

    use CheckExistingResourceTrait;

    public const OPERATIONS_NAME = ['get_dashboard_stats'];


    private EntityManagerInterface $entityManagerInterface;

    public function __construct(EntityManagerInterface $entityManagerInterface)
    {
        $this->entityManagerInterface = $entityManagerInterface;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return ((Review::class === $resourceClass) && in_array($operationName, self::OPERATIONS_NAME, true));
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $papersRepo = $this->entityManagerInterface->getRepository(Papers::class);
        $paperLogRepo = $this->entityManagerInterface->getRepository(PaperLog::class);
        $filters['is'] = $context['filters'] ?? [];

        $dashboard = [];

        if ($operationName === 'get_dashboard_stats') {
            // aggregate stats
            $dashboard['submissions'] = $papersRepo->getSubmissionsStat($filters);
            $dashboard ['submissionsDelay'] = $paperLogRepo->getDelayBetweenSubmissionAndLatestStatus($filters);
            $dashboard ['publicationsDelay'] = $paperLogRepo->getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);

            if (!isset($filters['is']['year'])) {
                $dashboard['users'] = $this->entityManagerInterface->getRepository(User::class)->getUserStats($filters['is']);
            }
        }

        yield $dashboard;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // TODO: Implement provide() method.
    }
}
