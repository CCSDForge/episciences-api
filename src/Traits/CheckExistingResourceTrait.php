<?php


namespace App\Traits;

trait CheckExistingResourceTrait
{
    use ToolsTrait;
    /**
     * @param string $className
     * @param array $criteria
     * @return object|null
     */
    final public function check(string $className, array $criteria): ?object
    {
        return $this->entityManagerInterface->getRepository($className)->findOneBy($criteria);

    }

    /**
     * @param array $available
     * @param array $requested
     * @return array
     */
    final public function checkFilters(array $available, array $requested = []): array
    {
        return $this->checkArrayEquality($available, array_keys($requested));
    }

}
