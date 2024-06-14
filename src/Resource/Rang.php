<?php

namespace App\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class Rang
{
    #[groups(
        ['read:News:Range', 'read:Volume:Range']
    )]
    private string $name = 'range';
    #[groups(
        ['read:News:Range', 'read:Volume:Range']
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

    public function setValues(array $values = []): self
    {
        $years = [];

        foreach ($values as $value) {
            if (isset($value['year'])) {
                if (empty($value)) {
                    continue;
                }
                $years[] = $value['year'];
            }
        }
        $this->values = $years;
        return $this;
    }

}