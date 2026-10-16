<?php

declare(strict_types=1);

namespace App\OpenApi;

/**
 * Reusable JSON schema fragments for the properties whose PHP type (array) does not describe the serialized JSON.
 *
 * An associative PHP array is serialized as a JSON object, but as an empty JSON array when it is empty:
 * "type: [object, array]" with "maxItems: 0" accepts both.
 */
final class OpenApiSchemas
{
    /** Text indexed by language code, e.g. {"en": "...", "fr": "..."} */
    public const array TRANSLATED_TEXT = [
        'type' => ['object', 'array'],
        'additionalProperties' => ['type' => 'string'],
        'maxItems' => 0,
        'example' => ['en' => 'English text', 'fr' => 'Texte en français'],
    ];

    public const array NULLABLE_TRANSLATED_TEXT = ['type' => ['object', 'array', 'null']] + self::TRANSLATED_TEXT;

    /** JSON object with free-form content */
    public const array OBJECT = ['type' => ['object', 'array'], 'maxItems' => 0];

    public const array NULLABLE_OBJECT = ['type' => ['object', 'array', 'null'], 'maxItems' => 0];

    /** Free-form value of a statistic: a number, or the details of the statistic */
    public const array STAT_VALUE = ['type' => ['object', 'array', 'number', 'null']];

    /** IRI of a related resource */
    public const array NULLABLE_IRI = ['type' => ['string', 'null'], 'format' => 'iri-reference', 'example' => '/api/users/1'];
}
