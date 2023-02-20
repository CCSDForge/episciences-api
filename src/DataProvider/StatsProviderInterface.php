<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use App\Resource\StatResource;

interface StatsProviderInterface
{
    public function supports(Operation $operation = null): bool;
    public function getCollection(Operation $operation, array $context = []): array | StatResource;
}
