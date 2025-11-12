<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailVerificationService
{
    private CacheRepository $cache;
    private int $ttl;
    private string $prefix;

    public function __construct(CacheFactory $cacheFactory)
    {
        $store = config('email_verification.store', 'redis');
        $this->cache = $cacheFactory->store($store);
        $this->ttl = (int) config('email_verification.ttl', 900);
        $this->prefix = config('email_verification.key_prefix', 'email_verifications:');
    }

    public function createToken(User $user): string
    {
        $token = (string) Str::uuid();

        $payload = [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'expires_at' => now()->addSeconds($this->ttl)->toIso8601String(),
        ];

        $this->cache->put($this->key($token), $payload, $this->ttl);

        return $token;
    }

    public function getPayload(string $token): ?array
    {
        $payload = $this->cache->get($this->key($token));

        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    public function delete(string $token): void
    {
        $this->cache->forget($this->key($token));
    }

    public function tokenExpired(array $payload): bool
    {
        try {
            $expiresAt = Carbon::parse($payload['expires_at'] ?? null);
        } catch (\Exception $exception) {
            Log::warning('Invalid expires_at for verification token', [
                'payload' => $payload,
                'error' => $exception->getMessage(),
            ]);

            return true;
        }

        return now()->greaterThan($expiresAt);
    }

    private function key(string $token): string
    {
        return $this->prefix . $token;
    }
}
