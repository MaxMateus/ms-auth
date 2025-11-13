<?php

namespace App\DTOs\Auth;

use App\Exceptions\Auth\InvalidTokenException;
use App\Http\Requests\Auth\RefreshTokenRequest;

/**
 * Representa a solicitação de refresh token.
 */
class RefreshTokenDTO
{
    public function __construct(
        public readonly string $tokenId,
        public readonly string $tokenValue,
    ) {
    }

    public static function fromRequest(RefreshTokenRequest $request): self
    {
        $authorization = $request->validated()['authorization'] ?? '';
        $tokenValue = trim(substr($authorization, 7));

        if ($tokenValue === '') {
            throw InvalidTokenException::becauseMissing();
        }

        return new self(
            tokenId: self::extractTokenId($tokenValue),
            tokenValue: $tokenValue,
        );
    }

    private static function extractTokenId(string $tokenValue): string
    {
        $parts = explode('.', $tokenValue);

        if (count($parts) !== 3) {
            throw InvalidTokenException::becauseMalformed();
        }

        $payload = json_decode(
            base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]), true) ?: '',
            true
        );

        if (!is_array($payload) || empty($payload['jti'])) {
            throw InvalidTokenException::becauseMalformed();
        }

        return (string) $payload['jti'];
    }
}
