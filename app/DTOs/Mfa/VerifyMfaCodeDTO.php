<?php

namespace App\DTOs\Mfa;

use App\Helpers\ContactFormatter;
use App\Http\Requests\Mfa\VerifyMfaCodeRequest;
use App\Http\Requests\Mfa\VerifyMfaLinkRequest;

class VerifyMfaCodeDTO
{
    public function __construct(
        public readonly string $method,
        public readonly string $destination,
        public readonly string $code,
    ) {
    }

    public static function fromRequest(VerifyMfaCodeRequest $request): self
    {
        $data = $request->validated();

        return self::fromArray($data);
    }

    public static function fromLinkRequest(VerifyMfaLinkRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param array{method:string,destination:string,code:string} $data
     */
    private static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'],
            destination: self::normalizeDestination($data['method'], $data['destination']),
            code: $data['code'],
        );
    }

    private static function normalizeDestination(string $method, string $destination): string
    {
        return $method === 'email'
            ? ContactFormatter::normalizeEmail($destination)
            : ContactFormatter::digitsOnly($destination);
    }
}
