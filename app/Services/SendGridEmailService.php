<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGridEmailService
{
    private const API_URL = 'https://api.sendgrid.com/v3/mail/send';

    public function sendVerificationCode(string $toEmail, string $code, ?string $toName = null): void
    {
        $apiKey = config('services.sendgrid.api_key');
        $fromEmail = config('services.sendgrid.from_email');
        $fromName = config('services.sendgrid.from_name', config('app.name', 'MS-Auth'));

        if (!$apiKey || !$fromEmail) {
            Log::warning('SendGrid configuration missing for MFA code');

            return;
        }

        $recipient = ['email' => $toEmail];

        if ($toName) {
            $recipient['name'] = $toName;
        }

        $verificationUrl = $this->buildVerificationUrl($toEmail, $code);

        $payload = [
            'personalizations' => [
                [
                    'to' => [$recipient],
                    'subject' => 'Seu código de verificação',
                ],
            ],
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName,
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $this->buildHtml($code, $verificationUrl),
                ],
            ],
        ];

        try {
            Http::withToken($apiKey)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::API_URL, $payload)
                ->throw();

            Log::info('SendGrid verification code dispatched', [
                'email' => $toEmail,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send SendGrid verification code', [
                'email' => $toEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildHtml(string $code, string $verificationUrl): string
    {
        return <<<HTML
<p>Olá!</p>
<p>Use o código abaixo para confirmar sua conta ou clique no botão para validar automaticamente:</p>
<p style="font-size:24px;font-weight:bold;">{$code}</p>
<p>
    <a href="{$verificationUrl}"
       style="background:#2563eb;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;margin:16px 0;">
        Confirmar e-mail agora
    </a>
</p>
<p>Se o botão não funcionar, copie e cole este link no navegador:</p>
<p><a href="{$verificationUrl}">{$verificationUrl}</a></p>
<p>O código expira em 5 minutos.</p>
HTML;
    }

    private function buildVerificationUrl(string $email, string $code): string
    {
        $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/');

        $query = http_build_query([
            'method' => 'email',
            'destination' => strtolower($email),
            'code' => $code,
        ]);

        return sprintf('%s/api/mfa/verify-link?%s', $baseUrl, $query);
    }
}
