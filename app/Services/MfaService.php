<?php

namespace App\Services;

use App\Models\MfaCode;
use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MfaService
{
    public function __construct(
        private readonly SendGridEmailService $sendGridEmailService,
        private readonly ZenviaSmsService $zenviaSmsService,
        private readonly WhatsappMetaService $whatsappMetaService,
    ) {
    }

    public function sendCode(User $user, string $method, string $destination): void
    {
        $code = $this->generateCode();

        DB::transaction(function () use ($user, $method, $destination, $code) {
            MfaMethod::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'method' => $method,
                ],
                [
                    'destination' => $destination,
                    'verified' => false,
                ]
            );

            MfaCode::query()
                ->where('user_id', $user->getKey())
                ->where('method', $method)
                ->where('destination', $destination)
                ->where('used', false)
                ->update(['used' => true]);

            MfaCode::query()->create([
                'user_id' => $user->getKey(),
                'method' => $method,
                'destination' => $destination,
                'code' => $code,
                'used' => false,
                'expires_at' => now()->addMinutes(5),
            ]);
        });

        $this->dispatchCode($method, $destination, $code, $user);
    }

    public function verifyCode(User $user, string $method, string $destination, string $code): MfaMethod
    {
        $mfaCode = MfaCode::query()
            ->where('user_id', $user->getKey())
            ->where('method', $method)
            ->where('destination', $destination)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$mfaCode) {
            throw ValidationException::withMessages([
                'code' => ['Código inválido ou expirado.'],
            ]);
        }

        return DB::transaction(function () use ($mfaCode, $user, $method, $destination) {
            $mfaCode->forceFill(['used' => true])->save();

            /** @var MfaMethod $methodModel */
            $methodModel = MfaMethod::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'method' => $method,
                ],
                [
                    'destination' => $destination,
                    'verified' => true,
                ]
            );

            $methodModel->forceFill([
                'verified' => true,
                'destination' => $destination,
            ])->save();

            return $methodModel;
        });
    }

    private function dispatchCode(string $method, string $destination, string $code, User $user): void
    {
        match ($method) {
            'email' => $this->sendGridEmailService->sendVerificationCode($destination, $code, $user->name),
            'sms' => $this->zenviaSmsService->sendVerificationCode($destination, $code),
            'whatsapp' => $this->whatsappMetaService->sendVerificationCode($destination, $code),
            default => null,
        };
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
