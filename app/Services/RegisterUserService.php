<?php

namespace App\Services;

use App\DTOs\Auth\RegisterUserDTO;
use App\DTOs\Mfa\SendMfaCodeDTO;
use App\Enums\UserStatus;
use App\Exceptions\InvalidCpfException;
use App\Exceptions\UserAlreadyExistsException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\CpfValidator;
use App\Support\TransactionManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MfaService $mfaService,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    public function register(RegisterUserDTO $dto): User
    {
        if (!CpfValidator::isValid($dto->cpf)) {
            throw new InvalidCpfException();
        }

        $conflicts = [];

        if ($this->userRepository->existsByEmail($dto->email)) {
            $conflicts['email'] = 'E-mail já cadastrado.';
        }

        if ($this->userRepository->existsByCpf($dto->cpf)) {
            $conflicts['cpf'] = 'CPF já cadastrado.';
        }

        if (!empty($conflicts)) {
            throw new UserAlreadyExistsException($conflicts);
        }

        $user = $this->transactionManager->run(function () use ($dto) {
            $hashedPassword = Hash::make($dto->password);
            $attributes = $dto->toUserAttributes($hashedPassword);
            $attributes['status'] = UserStatus::PendingVerification->value;

            $user = $this->userRepository->create($attributes);

            Log::info('User registered and verification code dispatched', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user;
        });

        $this->mfaService->sendCode(
            SendMfaCodeDTO::fromArray([
                'method' => 'email',
                'destination' => $user->email,
            ])
        );

        return $user;
    }
}
