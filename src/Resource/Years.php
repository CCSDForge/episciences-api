<?php

namespace App\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class Years
{
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private string $name = 'years';
    #[groups(
        ['read:News', 'read:News:Collection']
    )]
    private array $values = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): self
    {
        $this->values = $values;
        return $this;
    }

}