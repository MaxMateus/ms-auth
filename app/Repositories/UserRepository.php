<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Exceptions\UserNotFoundException;
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

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()
            ->where('email', $email)
            ->first();
    }

    public function findById(int $userId): ?User
    {
        return $this->model->newQuery()->find($userId);
    }

    public function requireById(int $userId): User
    {
        $user = $this->findById($userId);

        if (!$user) {
            throw UserNotFoundException::create();
        }

        return $user;
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

    public function activate(User $user): User
    {
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::Active->value,
        ])->save();

        return $user;
    }
}
