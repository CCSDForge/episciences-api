<?php

declare(strict_types=1);

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Main\PaperLog;
use App\Entity\Main\Papers;
use App\Entity\Main\Review;
use App\Resource\StatResource;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;

final class PapersStatsDataProvider implements ProviderInterface
{

    use CheckExistingResourceTrait;

    public const OPERATIONS_NAME = [
        'get_stats_nb_submissions',
        'get_delay_between_submit_and_acceptance',
        'get_delay_between_submit_and_publication',
    ];


    private EntityManagerInterface $entityManagerInterface;

    public function __construct(EntityManagerInterface $entityManagerInterface)
    {
        $this->entityManagerInterface = $entityManagerInterface;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return ((Papers::class === $resourceClass) && in_array($operationName, self::OPERATIONS_NAME, true));
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $filters['is'] = $context['filters'] ?? [];

        if (isset($filters['is']['rvid'])) {

            /** @var Review $review */
            $review = $this->check(Review::class, ['rvid' => $filters['is']['rvid']]);
            if (!$review || ($review->getStatus() !== 1)) {
                return yield (new StatResource($context['request_uri'], $filters['is']));
            }
        }

        if ($operationName === 'get_stats_nb_submissions') {
            // Le but des générateurs est de créer facilement des itérateurs
            yield $this->entityManagerInterface->getRepository(Papers::class)->getSubmissionsStat($filters);
        } elseif ($operationName === 'get_delay_between_submit_and_acceptance') {
            yield $this->entityManagerInterface->getRepository(PaperLog::class)->getDelayBetweenSubmissionAndLatestStatus($filters);
        } elseif ($operationName === 'get_delay_between_submit_and_publication') {
            yield $this->entityManagerInterface->getRepository(PaperLog::class)->getDelayBetweenSubmissionAndLatestStatus($filters, Papers::STATUS_PUBLISHED);
        }
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // TODO: Implement provide() method.
    }
}
