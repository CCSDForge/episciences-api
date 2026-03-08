<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\AppConstants;
use JsonException;
use Symfony\Component\Serializer\Attribute\Groups;

abstract class AbstractStatResource implements \Stringable
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): int|float|array|null
    {
        return $this->value;
    }

    /**
     * @return $this
     */
    public function setValue(int|float|array|null $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @return $this
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
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
