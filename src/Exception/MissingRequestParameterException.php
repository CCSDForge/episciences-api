<?php
namespace App\Exception;

use Exception;


final class MissingRequestParameterException extends Exception
{
    public static function new(string $name, string $type): self
    {
        return new self(
            sprintf('Required "%s" parameter in "%s" is not present.', $name, $type),
        );
    }

}