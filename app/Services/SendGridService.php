<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGridService
{
    private const API_URL = 'https://api.sendgrid.com/v3/mail/send';

    public function sendVerificationEmail(string $toEmail, string $toName, string $verificationUrl): void
    {
        $apiKey = config('services.sendgrid.api_key');
        $fromEmail = config('services.sendgrid.from_email');
        $fromName = config('services.sendgrid.from_name', config('app.name', 'MS-Auth'));

        if (!$apiKey || !$fromEmail) {
            Log::warning('SendGrid configuration missing');
            return;
        }

        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $toEmail, 'name' => $toName],
                    ],
                    'subject' => 'Confirme seu e-mail',
                ],
            ],
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName,
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $this->buildHtml($toName, $verificationUrl),
                ],
            ],
        ];

        try {
            Http::withToken($apiKey)
                ->acceptJson()
                ->post(self::API_URL, $payload)
                ->throw();
        } catch (\Throwable $exception) {
            Log::error('Failed to send SendGrid verification email', [
                'email' => $toEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildHtml(string $name, string $url): string
    {
        return <<<HTML
<p>Olá, {$name}!</p>
<p>Obrigado por se registrar. Confirme seu e-mail clicando no link abaixo:</p>
<p><a href="{$url}">Confirmar e-mail</a></p>
<p>Este link é válido por 15 minutos.</p>
HTML;
    }
}
