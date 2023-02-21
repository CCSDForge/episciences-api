<?php

declare(strict_types=1);

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AppConstants;
use App\Entity\Main\Papers;


final class PapersStatsDataProvider extends AbstractDataProvider implements ProviderInterface
{
    public function supports(Operation $operation = null): bool
    {
        return (
            $operation &&
            (Papers::class === $operation->getClass()) &&
            in_array($operation->getName(), AppConstants::APP_CONST['custom_operations']['items']['papers'], true)
        );
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->supports($operation) ? $this->getCollection($operation, $context) : null;
    }
}
