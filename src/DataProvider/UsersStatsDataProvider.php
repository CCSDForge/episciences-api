<?php

declare(strict_types=1);

namespace App\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Main\Review;
use App\Entity\Main\User;
use App\Repository\Main\UserRepository;
use App\Resource\StatResource;
use App\Traits\CheckExistingResourceTrait;
use Doctrine\ORM\EntityManagerInterface;

final class UsersStatsDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    use CheckExistingResourceTrait;

    public const OPERATIONS_NAME = [
        'get_stats_nb_users',
    ];

    private EntityManagerInterface $entityManagerInterface;

    public function __construct(EntityManagerInterface $entityManagerInterface)
    {
        $this->entityManagerInterface = $entityManagerInterface;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return ((User::class === $resourceClass) && in_array($operationName, self::OPERATIONS_NAME, true));
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {

        $filters['is'] = $context['filters'] ?? [];
        $userResource = new StatResource($context['request_uri'], $filters['is'], UserRepository::AVAILABLE_FILTERS);

        if (isset($filters['is']['roles.rvid'])) {
            /** @var Review $review */
            $review = $this->check(Review::class, ['rvid' => $filters['is']['roles.rvid']]);
            if (!$review || ($review->getStatus() !== 1)) {
                return yield $userResource;
            }
        }

        if ($operationName === 'get_stats_nb_users') {
            yield $this->entityManagerInterface->getRepository(User::class)->getUserStats($filters['is']);
        }
    }
}
