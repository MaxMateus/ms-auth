<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class InvalidTokenException extends RuntimeException
{
    public static function becauseMissing(): self
    {
        return new self('Token não fornecido.');
    }

    public static function becauseMalformed(): self
    {
        return new self('Token malformado.');
    }

    public static function becauseInvalid(): self
    {
        return new self('Token inválido ou expirado.');
    }
}
