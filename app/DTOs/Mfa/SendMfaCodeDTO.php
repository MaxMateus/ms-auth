<?php

namespace App\DTOs\Mfa;

use App\Helpers\ContactFormatter;
use App\Http\Requests\Mfa\SendMfaCodeRequest;

class SendMfaCodeDTO
{
    public function __construct(
        public readonly string $method,
        public readonly string $destination,
    ) {
    }

    public static function fromRequest(SendMfaCodeRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param array{method:string,destination:string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'],
            destination: self::normalizeDestination($data['method'], $data['destination']),
        );
    }

    private static function normalizeDestination(string $method, string $destination): string
    {
        return $method === 'email'
            ? ContactFormatter::normalizeEmail($destination)
            : ContactFormatter::digitsOnly($destination);
    }
}
