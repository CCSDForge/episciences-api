<?php

declare(strict_types=1);

namespace App\Exception;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Invalid query parameter checked by a provider (e.g. a combination of parameters): the same 422 error, with the
 * same body (`violations`), as the parameters validated by API Platform (QueryParameter).
 */
final class InvalidQueryParameter
{
    /**
     * @param string|list<string> $parameters one violation by parameter
     * @param string $message the violation, prefixed with the parameter in the error detail ("year: …")
     */
    public static function exception(string|array $parameters, string $message, mixed $invalidValue = null): ValidationException
    {
        $violations = new ConstraintViolationList();

        foreach ((array)$parameters as $parameter) {
            $violations->add(new ConstraintViolation($message, $message, [], null, $parameter, $invalidValue));
        }

        return new ValidationException($violations);
    }
}
