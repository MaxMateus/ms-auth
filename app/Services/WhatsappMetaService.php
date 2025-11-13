<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappMetaService
{
    private const GRAPH_URL = 'https://graph.facebook.com/v18.0';

    public function sendVerificationCode(string $phoneNumber, string $code): void
    {
        $accessToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$accessToken || !$phoneNumberId) {
            Log::warning('WhatsApp configuration missing');

            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhone($phoneNumber),
            'type' => 'text',
            'text' => [
                'body' => "Seu código de verificação é {$code}. Ele expira em 5 minutos.",
            ],
        ];

        $url = self::GRAPH_URL . "/{$phoneNumberId}/messages";

        try {
            Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, $payload)
                ->throw();
        } catch (\Throwable $exception) {
            Log::error('Failed to send WhatsApp verification code', [
                'phone' => $phoneNumber,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function formatPhone(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber) ?? '';

        return str_starts_with($digits, '55') ? $digits : '55' . $digits;
    }
}
