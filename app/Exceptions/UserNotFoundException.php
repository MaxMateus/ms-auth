<?php

namespace App\Exceptions;

use RuntimeException;

class UserNotFoundException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Usuário não encontrado.');
    }
}
