<?php

namespace App\Repository;

use App\Resource\Rang;

interface RangeInterface
{
    public function getRange(string|int $journalIdentifier = null): array ;

}