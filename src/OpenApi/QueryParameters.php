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

    /** Descriptions of the parameters of the search filters (#[ApiFilter]), which have none, by property */
    public const array FILTER_DESCRIPTIONS = [
        'rvid' => 'Journal identifier',
        'doi' => 'DOI of the paper',
        'paperid' => 'Identifier of the paper, shared by all its versions',
        'docid' => 'Identifier of a version of the paper (document identifier)',
        'vid' => 'Volume identifier',
        'sid' => 'Section identifier',
        'repoid' => 'Identifier of the repository hosting the paper',
        'flag' => 'Origin of the paper: `submitted` to the journal, or `imported`',
        'status' => 'Status of the paper in the editorial workflow (e.g. 16: published)',
        'uid' => 'User identifier (uid) of the page',
        'page_code' => 'Code of the page (e.g. about, for-authors)',
        'userRoles.rvid' => 'Journal identifier: the users having a role in this journal',
    ];

    /** Comparisons of the date filters (`property[comparison]`) */
    public const array DATE_COMPARISONS = [
        'before' => 'on or before',
        'strictly_before' => 'before',
        'after' => 'on or after',
        'strictly_after' => 'after',
    ];
}
