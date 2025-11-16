<?php

namespace App\Repositories;

use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MfaMethodRepository
{
    public function __construct(private readonly MfaMethod $model)
    {
    }

    public function upsert(User $user, string $method, string $destination, bool $verified): MfaMethod
    {
        /** @var MfaMethod $record */
        $record = $this->model->newQuery()->firstOrNew([
            'user_id' => $user->getKey(),
            'method' => $method,
        ]);

        $destinationChanged = $record->exists && $record->destination !== $destination;

        $record->destination = $destination;

        if (!$record->exists) {
            $record->verified = $verified;
        } elseif ($verified) {
            $record->verified = true;
        } elseif ($destinationChanged) {
            $record->verified = false;
        }

        $record->save();

        return $record;
    }

    public function markAsVerified(MfaMethod $method, string $destination): MfaMethod
    {
        $method->forceFill([
            'verified' => true,
            'destination' => $destination,
        ])->save();

        return $method;
    }

    public function methodIsVerified(int $userId, string $method): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('method', $method)
            ->where('verified', true)
            ->exists();
    }

    public function listForUser(User $user): Collection
    {
        return $this->model->newQuery()
            ->where('user_id', $user->getKey())
            ->get(['id', 'method', 'destination', 'verified', 'created_at', 'updated_at']);
    }
}
