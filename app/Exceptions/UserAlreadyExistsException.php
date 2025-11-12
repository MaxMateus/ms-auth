<?php

namespace App\Exceptions;

use RuntimeException;

class UserAlreadyExistsException extends RuntimeException
{
    /**
     * @param array<string, string> $conflicts
     */
    public function __construct(private readonly array $conflicts)
    {
        parent::__construct('Usuário já cadastrado no sistema.');
    }

    /**
     * @return array<string, string>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
