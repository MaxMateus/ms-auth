<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\PersonalAccessTokenResult;
use Laravel\Passport\Token;

class PassportTokenRepository
{
    public function __construct(private readonly Token $model)
    {
    }

    public function findActiveById(string $tokenId): ?Token
    {
        return $this->model->newQuery()
            ->where('id', $tokenId)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revoke(string $tokenId): void
    {
        DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => true]);
    }

    public function create(User $user, string $tokenName = 'authToken'): PersonalAccessTokenResult
    {
        return $user->createToken($tokenName);
    }
}
