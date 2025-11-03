<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Passport\Token;

class TokenCacheService
{
    private const CACHE_PREFIX = 'auth:token:';

    /**
     * Store token metadata in Redis so we can validate/revoke it quickly.
     *
     * @param \Laravel\Passport\Token $token
     * @param array $payload
     */
    public function store(Token $token, array $payload): void
    {
        $ttl = now()->diffInSeconds($token->expires_at, false);
        $ttl = $ttl > 0 ? $ttl : 1;

        $payload = array_merge($payload, [
            'token_id' => $token->id,
            'expires_at' => $token->expires_at->toISOString(),
        ]);

        try {
            Redis::setex($this->key($token->id), $ttl, json_encode($payload));
        } catch (\Throwable $exception) {
            Log::warning('Failed to cache token in Redis', [
                'token_id' => $token->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Retrieve token metadata from Redis.
     */
    public function get(string $tokenId): ?array
    {
        try {
            $value = Redis::get($this->key($tokenId));
        } catch (\Throwable $exception) {
            Log::warning('Failed to recover token from Redis', [
                'token_id' => $tokenId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!$value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Remove token metadata from Redis.
     */
    public function forget(string $tokenId): void
    {
        try {
            Redis::del($this->key($tokenId));
        } catch (\Throwable $exception) {
            Log::warning('Failed to remove token from Redis', [
                'token_id' => $tokenId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function key(string $tokenId): string
    {
        return self::CACHE_PREFIX . $tokenId;
    }
}

