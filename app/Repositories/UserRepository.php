<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    public function __construct(private readonly User $model)
    {
    }

    public function existsByEmail(string $email): bool
    {
        return $this->model->newQuery()
            ->where('email', $email)
            ->exists();
    }

    public function existsByCpf(string $cpf): bool
    {
        return $this->model->newQuery()
            ->where('cpf', $cpf)
            ->exists();
    }

    public function create(array $attributes): User
    {
        try {
            return $this->model->newQuery()->create($attributes);
        } catch (\Throwable $exception) {
            Log::error('Failed to create user', [
                'email' => $attributes['email'] ?? null,
                'cpf' => $attributes['cpf'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
