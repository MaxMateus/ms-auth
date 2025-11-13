<?php

namespace App\DTOs\Auth;

use App\Helpers\ContactFormatter;
use App\Http\Requests\Auth\LoginRequest;

/**
 * Dados estruturados para login.
 */
class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromRequest(LoginRequest $request): self
    {
        $data = $request->validated();

        return new self(
            email: ContactFormatter::normalizeEmail($data['email']),
            password: $data['password'],
        );
    }
}
