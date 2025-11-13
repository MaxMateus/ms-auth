<?php

namespace App\Http\Controllers;

use App\DTOs\Mfa\SendMfaCodeDTO;
use App\DTOs\Mfa\VerifyMfaCodeDTO;
use App\Exceptions\Mfa\InvalidMfaCodeException;
use App\Exceptions\Mfa\MfaMethodNotFoundException;
use App\Http\Requests\Mfa\SendMfaCodeRequest;
use App\Http\Requests\Mfa\VerifyMfaCodeRequest;
use App\Http\Requests\Mfa\VerifyMfaLinkRequest;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MfaController extends Controller
{
    public function __construct(private readonly MfaService $mfaService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'methods' => $this->mfaService->listMethods($user),
        ]);
    }

    public function send(SendMfaCodeRequest $request): JsonResponse
    {
        $this->mfaService->sendCode(
            SendMfaCodeDTO::fromRequest($request),
            $request->user('api') ?? $request->user()
        );

        return response()->json([
            'message' => 'Código enviado com sucesso.',
        ]);
    }

    public function verify(VerifyMfaCodeRequest $request): JsonResponse
    {
        $methodModel = $this->mfaService->verifyCode(
            VerifyMfaCodeDTO::fromRequest($request),
            $request->user('api') ?? $request->user()
        );

        return response()->json([
            'message' => 'Método verificado com sucesso.',
            'method' => $methodModel->only(['id', 'method', 'destination', 'verified']),
        ]);
    }

    public function verifyLink(VerifyMfaLinkRequest $request)
    {
        try {
            $this->mfaService->verifyCode(VerifyMfaCodeDTO::fromLinkRequest($request));
        } catch (MfaMethodNotFoundException|InvalidMfaCodeException $exception) {
            return $this->verificationView(false, $exception->getMessage(), $exception instanceof MfaMethodNotFoundException ? $exception->status() : 422);
        } catch (\Throwable $exception) {
            Log::error('Failed to verify MFA code via link', [
                'error' => $exception->getMessage(),
            ]);

            return $this->verificationView(false, 'Não foi possível verificar o código.', 500);
        }

        return $this->verificationView(true, 'Método verificado com sucesso.');
    }

    private function verificationView(bool $success, string $message, int $status = 200)
    {
        $color = $success ? '#047857' : '#b91c1c';
        $headline = $success ? 'Tudo certo!' : 'Ops!';
        $secondary = $success
            ? 'Você já pode retornar ao app e fazer login.'
            : 'Solicite um novo código e tente novamente.';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de segurança</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5; color: #111; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); max-width: 420px; width: 90%; text-align: center; }
        .status { font-size: 18px; margin-bottom: 12px; color: {$color}; font-weight: 600; }
        p { margin: 0 0 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <main class="card">
        <div class="status">{$headline}</div>
        <p>{$message}</p>
        <p>{$secondary}</p>
    </main>
</body>
</html>
HTML;

        return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
