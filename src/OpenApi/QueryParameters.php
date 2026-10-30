<?php

declare(strict_types=1);

namespace App\OpenApi;

/**
 * Descriptions and schemas shared by the query parameters declared in the operations.
 *
 * A filter taking several values is declared once, as `name[]` with a list schema: OpenApiFactory adds its syntax note.
 */
final class QueryParameters
{
    public const string JOURNAL_CODE_DESCRIPTION = 'Journal code (e.g. epijinfo)';

    public const string START_AFTER_DATE_DESCRIPTION = 'Start statistics after date [YYYY-MM-DD]: a wrong format is rejected (404)';

    public const array STRING = ['type' => 'string'];

    public const array INTEGER = ['type' => 'integer'];

    public const array BOOLEAN = ['type' => 'boolean'];

    public const array DATE = ['type' => 'string', 'format' => 'date'];

    public const array STRINGS = ['type' => 'array', 'items' => self::STRING];

    public const array INTEGERS = ['type' => 'array', 'items' => self::INTEGER];
}
