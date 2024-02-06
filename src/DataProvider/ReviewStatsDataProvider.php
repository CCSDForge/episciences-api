<?php

declare(strict_types=1);

namespace App\DataProvider;


use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Review;

final class ReviewStatsDataProvider extends AbstractDataProvider implements ProviderInterface
{

    public const AVAILABLE_FILTERS = [AppConstants::START_AFTER_DATE, AppConstants::WITH_DETAILS];


    public function supports(Operation $operation = null): bool
    {
        return (
            $operation &&
            (Review::class === $operation->getClass()) &&
            in_array($operation->getName(), AppConstants::APP_CONST['custom_operations']['items']['review'], true)
        );
    }


    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->supports($operation) ? $this->getCollection($operation, $context) : null;
    }
}
