<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class AccountNotVerifiedException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Conta ainda não ativada. Verifique seu e-mail antes de fazer login.');
    }
}
