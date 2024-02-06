<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\AppConstants;
use JsonException;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class AbstractStatResource
{

    public function __construct(

        #[ApiProperty(
            openapiContext: [
                'type' => 'array',
                'enum' => [AppConstants::WITH_DETAILS],
                'example' => AppConstants::WITH_DETAILS
            ]
        )]
        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private array  $availableFilters = [],

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private array  $requestedFilters = [],

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private string $name = '',

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private        $value = null,

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private        $details = null

    )
    {
    }

    public function getAvailableFilters(): array
    {
        return $this->availableFilters;
    }

    public function setAvailableFilters(array $availableFilters): self
    {
        $this->availableFilters = $availableFilters;
        return $this;
    }

    public function getRequestedFilters(): array
    {

        if (array_key_exists('page', $this->requestedFilters)) { // not yet available
            unset($this->requestedFilters['page']);
        }

        return $this->requestedFilters;
    }

    public function setRequestedFilters(array $filters): self
    {
        $this->requestedFilters = $filters;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return float|array|null
     */
    public function getValue(): float|array|null
    {
        return $this->value;
    }

    /**
     * @param float|array|null $value
     * @return $this
     */
    public function setValue(float|array|null $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param array|null $details
     * @return $this
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
     * @return string
     * @throws JsonException
     */
    public function __toString(): string
    {
        return
            sprintf(
                "The resource \"%s\" requested with the following filters: %s",
                $this->getName(), json_encode($this->getRequestedFilters(), JSON_THROW_ON_ERROR)
            );
    }
}