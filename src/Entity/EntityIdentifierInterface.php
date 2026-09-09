<?php

declare(strict_types=1);

namespace App\Entity;

interface EntityIdentifierInterface
{
    public function getIdentifier(): ?int;

}
