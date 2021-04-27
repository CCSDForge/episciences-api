<?php

declare(strict_types=1);

namespace App\Resource;

use Exception;
use Symfony\Component\Serializer\Annotation\Groups;


final class StatResource
{
    /** * @Groups({"papers_read"}) */
    private array $_availableFilters;

    /** * @Groups({"papers_read"}) */
    private array $_requestedFilters;

    /** * @Groups({"papers_read"}) */
    private string $_name;

    /** * @Groups({"papers_read"}) */
    private ?float $_value;

    /** * @Groups({"papers_read"}) */
    private ?array $_details;

    public function __construct(string $name = '', array $requestedFilters = [],  $availableFilters = [], float $value = null, $details = null)
    {
        $this->_availableFilters = $availableFilters;
        $this->_requestedFilters = $requestedFilters;
        $this->_name = $name;
        $this->_value = $value;
        $this->_details = $details;
    }

    public function getAvailableFilters(): array
    {
        return $this->_availableFilters;
    }

    public function setAvailableFilters(array $availableFilters): void
    {
        $this->_availableFilters = $availableFilters;
    }

    public function getRequestedFilters(): array
    {
        return $this->_requestedFilters;
    }

    public function setRequestedFilters(array $filters): void
    {
        $this->_requestedFilters = $filters;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->_name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    /**
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->_value;
    }

    /**
     * @param float|null $value
     */
    public function setValue(?float $value): void
    {
        $this->_value = $value;
    }

    /**
     * @return array
     */
    public function getDetails(): ?array
    {
        return $this->_details;
    }

    /**
     * @param array|null $details
     */
    public function setDetails(?array $details): void
    {
        $this->_details = $details;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function __toString(): string
    {
        return sprintf("The resource \"%s\" requested with the following filters: %s", $this->getName(), json_encode($this->getRequestedFilters(), JSON_THROW_ON_ERROR));
    }

}