<?php

declare(strict_types=1);

namespace App\Security;

/**
 * A delegated token request is refused (see DelegatedTokenIssuer): the reason is logged, never returned.
 */
final class InvalidDelegationException extends \RuntimeException
{
}
