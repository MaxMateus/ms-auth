<?php

namespace App\DTOs\Auth;

use App\Helpers\ContactFormatter;
use App\Http\Requests\Auth\RegisterRequest;

class RegisterUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $cpf,
        public readonly string $phone,
        public readonly string $birthdate,
        public readonly string $gender,
        public readonly bool $acceptTerms,
        public readonly string $street,
        public readonly string $number,
        public readonly ?string $complement,
        public readonly string $neighborhood,
        public readonly string $city,
        public readonly string $state,
        public readonly string $zipCode,
    ) {
    }

    public static function fromRequest(RegisterRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            email: ContactFormatter::normalizeEmail($data['email']),
            password: $data['password'],
            cpf: ContactFormatter::digitsOnly($data['cpf']),
            phone: ContactFormatter::digitsOnly($data['phone']),
            birthdate: $data['birthdate'],
            gender: $data['gender'],
            acceptTerms: (bool) $data['accept_terms'],
            street: trim($data['street']),
            number: trim($data['number']),
            complement: isset($data['complement']) && $data['complement'] !== null
                ? trim($data['complement'])
                : null,
            neighborhood: trim($data['neighborhood']),
            city: trim($data['city']),
            state: strtoupper(trim($data['state'])),
            zipCode: ContactFormatter::digitsOnly($data['zip_code']),
        );
    }

    public function toUserAttributes(string $hashedPassword): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $hashedPassword,
            'cpf' => $this->cpf,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
            'gender' => $this->gender,
            'accept_terms' => $this->acceptTerms,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
        ];
    }

}
