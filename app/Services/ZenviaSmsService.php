<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZenviaSmsService
{
    private const API_URL = 'https://api.zenvia.com/v2/channels/sms/messages';

    public function sendVerificationCode(string $phoneNumber, string $code): void
    {
        $token = config('services.zenvia.token');
        $from = config('services.zenvia.from');

        if (!$token || !$from) {
            Log::warning('Zenvia configuration missing');

            return;
        }

        $payload = [
            'from' => $from,
            'to' => $this->formatPhone($phoneNumber),
            'contents' => [
                [
                    'type' => 'text',
                    'text' => "Seu código de verificação é {$code}. Ele expira em 5 minutos.",
                ],
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-TOKEN' => $token,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, $payload);

            $response->throw();

            Log::info('Zenvia verification code dispatched', [
                'phone' => $phoneNumber,
                'status' => $response->status(),
                'message_id' => data_get($response->json(), 'id'),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send Zenvia verification code', [
                'phone' => $phoneNumber,
                'error' => $exception->getMessage(),
                'response' => method_exists($exception, 'response') ? optional($exception->response)->body() : null,
            ]);
        }
    }

    private function formatPhone(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber) ?? '';

        return str_starts_with($digits, '55') ? $digits : '55' . $digits;
    }
}
