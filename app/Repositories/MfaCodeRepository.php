<?php

namespace App\Repositories;

use App\Models\MfaCode;
use App\Models\User;
use Carbon\CarbonImmutable;

class MfaCodeRepository
{
    public function __construct(private readonly MfaCode $model)
    {
    }

    public function invalidateExisting(User $user, string $method, string $destination): void
    {
        $this->model->newQuery()
            ->where('user_id', $user->getKey())
            ->where('method', $method)
            ->where('destination', $destination)
            ->where('used', false)
            ->update(['used' => true]);
    }

    public function create(User $user, string $method, string $destination, string $code, CarbonImmutable $expiresAt): MfaCode
    {
        return $this->model->newQuery()->create([
            'user_id' => $user->getKey(),
            'method' => $method,
            'destination' => $destination,
            'code' => $code,
            'used' => false,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValid(User $user, string $method, string $destination, string $code): ?MfaCode
    {
        return $this->model->newQuery()
            ->where('user_id', $user->getKey())
            ->where('method', $method)
            ->where('destination', $destination)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markAsUsed(MfaCode $mfaCode): void
    {
        $mfaCode->forceFill(['used' => true])->save();
    }
}
