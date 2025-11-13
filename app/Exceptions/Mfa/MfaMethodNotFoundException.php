<?php

namespace App\Exceptions\Mfa;

use RuntimeException;

class MfaMethodNotFoundException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function forEmail(): self
    {
        return new self('Usuário não encontrado para este e-mail.', 404);
    }

    public static function requiresAuthentication(): self
    {
        return new self('Autenticação obrigatória para este método.', 401);
    }

    public function status(): int
    {
        return $this->status;
    }
}
