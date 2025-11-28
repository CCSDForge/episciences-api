<?php

namespace App\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class Range
{
    #[groups(
        ['read:News:Range', 'read:Volume:Range']
    )]
    protected string $name = 'range';
    #[groups(
        ['read:News:Range', 'read:Volume:Range']
    )]
    protected array $years = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getYears(): array
    {
        return $this->years;
    }

    public function setYears(array $result = []): self
    {
        $this->years = $result;
        return $this;
    }


}
