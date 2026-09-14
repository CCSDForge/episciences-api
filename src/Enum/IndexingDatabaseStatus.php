<?php

declare(strict_types=1);

namespace App\Enum;

enum IndexingDatabaseStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
}
