<?php

declare(strict_types=1);

namespace App\Repository;

use App\Resource\Range;

interface RangeInterface
{
    public const RANGE = 'range';
    public function getRange(string|int $journalIdentifier = null): array ;

}
