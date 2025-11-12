<?php

namespace App\Jobs;

use App\Services\SendGridService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly string $token
    ) {
    }

    public function handle(SendGridService $sendGridService): void
    {
        $verificationUrl = $this->buildVerificationUrl($this->token);
        $sendGridService->sendVerificationEmail($this->email, $this->name, $verificationUrl);
    }

    private function buildVerificationUrl(string $token): string
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return $baseUrl . '/api/auth/verify-email?token=' . $token;
    }
}
