<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Credenciais inválidas.');
    }
}
