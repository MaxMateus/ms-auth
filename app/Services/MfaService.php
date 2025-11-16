<?php

namespace App\Services;

use App\DTOs\Mfa\SendMfaCodeDTO;
use App\DTOs\Mfa\VerifyMfaCodeDTO;
use App\Exceptions\Mfa\InvalidMfaCodeException;
use App\Exceptions\Mfa\MfaMethodNotFoundException;
use App\Jobs\DispatchMfaCodeJob;
use App\Models\MfaMethod;
use App\Models\User;
use App\Repositories\MfaCodeRepository;
use App\Repositories\MfaMethodRepository;
use App\Repositories\UserRepository;
use App\Support\TransactionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class MfaService
{
    private const CODE_TTL_MINUTES = 5;

    public function __construct(
        private readonly MfaCodeRepository $mfaCodeRepository,
        private readonly MfaMethodRepository $mfaMethodRepository,
        private readonly UserRepository $userRepository,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    public function sendCode(SendMfaCodeDTO $dto, ?User $authenticatedUser = null): void
    {
        $user = $this->resolveUser($dto->method, $dto->destination, $authenticatedUser);
        $code = $this->generateCode();

        $this->transactionManager->run(function () use ($user, $dto, $code) {
            $this->mfaMethodRepository->upsert($user, $dto->method, $dto->destination, false);
            $this->mfaCodeRepository->create($user, $dto->method, $dto->destination, $code, $this->expiresAt());
        });

        $this->queueDispatch($dto->method, $dto->destination, $code, $user->name);
    }

    public function verifyCode(VerifyMfaCodeDTO $dto, ?User $authenticatedUser = null): MfaMethod
    {
        $user = $this->resolveUser($dto->method, $dto->destination, $authenticatedUser);
        $mfaCode = $this->mfaCodeRepository->findValid($user, $dto->method, $dto->destination, $dto->code);

        if (!$mfaCode) {
            throw InvalidMfaCodeException::create();
        }

        /** @var MfaMethod $method */
        $method = $this->transactionManager->run(function () use ($mfaCode, $user, $dto) {
            $this->mfaCodeRepository->markAsUsed($mfaCode);

            $methodModel = $this->mfaMethodRepository->upsert(
                $user,
                $dto->method,
                $dto->destination,
                true,
            );

            return $this->mfaMethodRepository->markAsVerified($methodModel, $dto->destination);
        });

        if ($dto->method === 'email') {
            $this->userRepository->activate($user);
        }

        return $method;
    }

    public function listMethods(User $user): Collection
    {
        return $this->mfaMethodRepository->listForUser($user);
    }

    private function resolveUser(string $method, string $destination, ?User $authenticatedUser): User
    {
        if ($method === 'email') {
            $user = $this->userRepository->findByEmail($destination);

            if (!$user) {
                throw MfaMethodNotFoundException::forEmail();
            }

            return $user;
        }

        if (!$authenticatedUser) {
            throw MfaMethodNotFoundException::requiresAuthentication();
        }

        return $authenticatedUser;
    }

    private function queueDispatch(string $method, string $destination, string $code, ?string $userName = null): void
    {
        DispatchMfaCodeJob::dispatch($method, $destination, $code, $userName);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes(self::CODE_TTL_MINUTES);
    }
}
