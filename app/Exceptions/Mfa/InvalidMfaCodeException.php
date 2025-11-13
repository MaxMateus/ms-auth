<?php

namespace App\Exceptions\Mfa;

use RuntimeException;

class InvalidMfaCodeException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Código inválido ou expirado.');
    }
}
