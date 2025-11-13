<?php

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RefreshTokenDTO;
use App\Exceptions\Auth\AccountNotVerifiedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Models\User;
use App\Repositories\MfaMethodRepository;
use App\Repositories\PassportTokenRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;

class AuthenticationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MfaMethodRepository $mfaMethodRepository,
        private readonly TokenCacheService $tokenCacheService,
        private readonly PassportTokenRepository $passportTokenRepository,
    ) {
    }

    /**
     * @return array{token:string,user:User}
     */
    public function login(LoginDTO $dto): array
    {
        if (!Auth::attempt(['email' => $dto->email, 'password' => $dto->password])) {
            throw InvalidCredentialsException::create();
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            throw InvalidCredentialsException::create();
        }

        if (!$this->mfaMethodRepository->methodIsVerified($user->getKey(), 'email')) {
            Auth::logout();
            throw AccountNotVerifiedException::create();
        }

        $tokenResult = $this->passportTokenRepository->create($user);
        $this->cacheToken($tokenResult->token, $user->getKey());

        return [
            'token' => $tokenResult->accessToken,
            'user' => $user,
        ];
    }

    public function logout(?User $user): void
    {
        if (!$user) {
            throw InvalidTokenException::becauseInvalid();
        }

        /** @var Token|null $token */
        $token = $user->token();

        if (!$token) {
            throw InvalidTokenException::becauseInvalid();
        }

        $this->tokenCacheService->forget($token->id);
        $token->revoke();
    }

    /**
     * @return array{token:string}
     */
    public function refresh(RefreshTokenDTO $dto): array
    {
        $cached = $this->tokenCacheService->get($dto->tokenId);
        $activeToken = $this->passportTokenRepository->findActiveById($dto->tokenId);

        if (!$activeToken) {
            $this->tokenCacheService->forget($dto->tokenId);
            throw InvalidTokenException::becauseInvalid();
        }

        $userId = $cached['user_id'] ?? $activeToken->user_id;
        $user = $this->userRepository->requireById((int) $userId);

        $this->passportTokenRepository->revoke($dto->tokenId);
        $this->tokenCacheService->forget($dto->tokenId);

        $newTokenResult = $this->passportTokenRepository->create($user);
        $this->cacheToken($newTokenResult->token, $user->getKey());

        return [
            'token' => $newTokenResult->accessToken,
        ];
    }

    private function cacheToken(Token $token, int $userId): void
    {
        $this->tokenCacheService->store($token, [
            'user_id' => $userId,
            'client_id' => $token->client_id,
            'scopes' => $token->scopes,
        ]);
    }
}
