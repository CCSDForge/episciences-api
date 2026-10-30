<?php

namespace App\Resource;

use App\OpenApi\OpenApiSchemas;
use ApiPlatform\Metadata\ApiProperty;
use App\AppConstants;
use JsonException;
use Symfony\Component\Serializer\Attribute\Groups;

abstract class AbstractStatResource implements \Stringable
{

    public function __construct(

        #[ApiProperty(
            description: 'The filters the current statistic supports (e.g. `withDetails`, `year`), each as its query parameter name',
            schema: [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'example' => [AppConstants::WITH_DETAILS, AppConstants::YEAR_PARAM],
            ]
        )]
        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        private array  $availableFilters = [],

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        #[ApiProperty(description: 'The filters received in the request and applied to compute the statistic', schema: OpenApiSchemas::OBJECT)]
        private array  $requestedFilters = [],

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        #[ApiProperty(description: 'Identifier of the statistic (e.g. `dashboard`, `nb-submissions`, `acceptance-rate`, a publication or evaluation indicator)')]
        private string $name = '',

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        #[ApiProperty(description: 'Computed value of the statistic: a number or the detailed breakdown (see each statistic for its schema)')]
        private        $value = null,

        #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['item']['read'][0]])]
        #[ApiProperty(description: 'Detailed breakdown of the statistic, returned when the `withDetails` filter is set', schema: OpenApiSchemas::NULLABLE_OBJECT)]
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
     * The schema of the value is documented by each subclass, with a class-level ApiProperty attribute
     * @return int|float|array|null
     */
    public function getValue(): int|float|array|null
    {
        return $this->value;
    }

    /**
     * @param int|float|array|null $value
     * @return $this
     */
    public function setValue(int|float|array|null $value): self
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
