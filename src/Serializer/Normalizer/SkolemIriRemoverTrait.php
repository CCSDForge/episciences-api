<?php

namespace App\Serializer\Normalizer;

use ApiPlatform\Symfony\Routing\SkolemIriConverter;

/**
 * Generated IRIs (/.well-known/genid/...) cannot be dereferenced and change on every request: blank nodes instead.
 */
trait SkolemIriRemoverTrait
{
    private function removeSkolemIri(mixed $data): mixed
    {
        if (
            is_array($data) &&
            isset($data['@id']) &&
            is_string($data['@id']) &&
            str_contains($data['@id'], strstr(SkolemIriConverter::$skolemUriTemplate, '{id}', true))
        ) {
            unset($data['@id']);
        }

        return $data;
    }
}
