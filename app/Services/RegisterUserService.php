<?php

namespace App\Services;

use App\DTOs\RegisterUserDTO;
use App\Enums\UserStatus;
use App\Exceptions\InvalidCpfException;
use App\Exceptions\UserAlreadyExistsException;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\CpfValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationService $emailVerificationService,
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

        return DB::transaction(function () use ($dto) {
            $hashedPassword = Hash::make($dto->password);
            $attributes = $dto->toUserAttributes($hashedPassword);
            $attributes['status'] = UserStatus::PendingVerification->value;

            $user = $this->userRepository->create($attributes);

            $token = $this->emailVerificationService->createToken($user);
            SendVerificationEmailJob::dispatch($user->name, $user->email, $token);

            Log::info('User registered and verification email dispatched', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user;
        });
    }
}
