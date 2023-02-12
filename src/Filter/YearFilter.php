<?php


namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

final class YearFilter extends  AbstractFilter
{

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {

        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName($property); // Generate a unique parameter name to avoid collisions with other filters
        $queryBuilder
            ->andWhere(sprintf('YEAR(o.%s) =:%s', $property, $parameterName))
            ->setParameter($parameterName, $value);
    }

    // This function is only used to hook in documentation generators (supported by Swagger and Hydra)
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'int',
                'required' => false,
                'swagger' => [
                    'description' => 'Filter using the year of first submission!',
                    'name' => 'year',
                    'type' => 'custom filter',
                ],
            ];
        }

        return $description;
    }
}