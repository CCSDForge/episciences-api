<?php

declare(strict_types=1);

namespace App\Resource;


use ApiPlatform\Action\NotFoundAction;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\AppConstants;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;



#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/review/stats/dashboard/{id}/',

            controller: NotFoundAction::class,
            openapi: new OpenApiOperation(
                summary: 'hidden',
            ),
            normalizationContext: [
                'groups' => ['read:StatResource']

            ],
            output: false,
            read: false,

        ),

    ]


)]
final class StatResource
{
    private string $id;
    #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['read'][0]])]
    private array $availableFilters;

    #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['read'][0]])]
    private array $requestedFilters;

    #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['read'][0]])]
    private string $name;

    #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['read'][0]])]
    private float|array|null $value;

    #[Groups([AppConstants::APP_CONST['normalizationContext']['groups']['review']['read'][0]])]
    private ?array $details;

    public function __construct(string $name = '', array $requestedFilters = [], $availableFilters = [], float $value = null, $details = null)
    {
        $this->availableFilters = $availableFilters;
        $this->requestedFilters = $requestedFilters;
        $this->name = $name;
        $this->value = $value;
        $this->details = $details;
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
     * @return StatResource
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
     * @return StatResource
     */
    public function setValue(float|array|null $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array|null $details
     * @return StatResource
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function __toString(): string
    {
        return sprintf("The resource \"%s\" requested with the following filters: %s", $this->getName(), json_encode($this->getRequestedFilters(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id = ''): self
    {
        $this->id = $id;
        return $this;
    }

}