<?php

namespace App\Jobs;

use App\Helpers\ContactFormatter;
use App\Services\SendGridEmailService;
use App\Services\WhatsappMetaService;
use App\Services\ZenviaSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchMfaCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $method,
        private readonly string $destination,
        private readonly string $code,
        private readonly ?string $userName = null,
    ) {
    }

    public function handle(
        SendGridEmailService $sendGridEmailService,
        ZenviaSmsService $zenviaSmsService,
        WhatsappMetaService $whatsappMetaService,
    ): void {
        try {
            match ($this->method) {
                'email' => $sendGridEmailService->sendVerificationCode($this->destination, $this->code, $this->userName),
                'sms' => $zenviaSmsService->sendVerificationCode($this->formatPhone(), $this->code),
                'whatsapp' => $whatsappMetaService->sendVerificationCode($this->formatPhone(), $this->code),
                default => null,
            };
        } catch (\Throwable $exception) {
            Log::error('Failed to dispatch MFA code', [
                'method' => $this->method,
                'destination' => $this->destination,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function formatPhone(): string
    {
        return ContactFormatter::normalizePhone($this->destination);
    }
}
