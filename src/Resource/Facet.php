<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

class Facet
{
    #[groups(['read:Browse:Authors'])]
    private string $field;
    #[groups(['read:Browse:Authors'])]
    #[ApiProperty(schema: [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'example' => 'Doe, John'],
            'count' => ['type' => 'integer', 'example' => 2],
        ],
    ])]
    private array $values;

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }
}