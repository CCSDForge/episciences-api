<?php

namespace App\Resource;
use Symfony\Component\Serializer\Annotation\Groups;

class RangeType extends Range
{
    protected string $name = 'range-type';
    #[groups(
        ['read:News:Range', 'read:Volume:Range']
    )]
    protected array $types = [];

    public function getTypes(): array
    {
        return $this->types;
    }

    public function setTypes(array $types): self
    {
        $this->types = $types;
        return $this;
    }

}