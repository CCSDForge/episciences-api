<?php


namespace App\Enum;

enum IndexingDatabaseStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
}
