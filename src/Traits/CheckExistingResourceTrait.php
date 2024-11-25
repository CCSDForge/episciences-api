<?php


namespace App\Traits;

use Doctrine\ORM\EntityManagerInterface;

trait CheckExistingResourceTrait
{
    public function __construct( private readonly EntityManagerInterface $entityManagerInterface){

    }
    use ToolsTrait;

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
