<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\MfaMethod;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function __construct(private readonly MfaService $mfaService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'methods' => $user->mfaMethods()
                ->get(['id', 'method', 'destination', 'verified', 'created_at', 'updated_at']),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'destination' => ['required', 'string'],
        ]);

        $method = $data['method'];
        $destination = $this->normalizeDestination($method, $data['destination']);

        try {
            $user = $this->resolveUserForMethod($request, $method, $destination);
        } catch (AuthenticationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        try {
            $this->mfaService->sendCode($user, $method, $destination);
        } catch (\Throwable $exception) {
            Log::error('Failed to send MFA code', [
                'method' => $method,
                'destination' => $destination,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível enviar o código. Tente novamente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Código enviado com sucesso.',
        ], 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'destination' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $method = $data['method'];
        $destination = $this->normalizeDestination($method, $data['destination']);
        $code = $data['code'];

        try {
            $methodModel = $this->performVerification($request, $method, $destination, $code);
        } catch (AuthenticationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Código inválido ou expirado.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Failed to verify MFA code', [
                'method' => $method,
                'destination' => $destination,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível verificar o código.',
            ], 500);
        }

        return response()->json([
            'message' => 'Método verificado com sucesso.',
            'method' => $methodModel->only(['id', 'method', 'destination', 'verified']),
        ]);
    }

    public function verifyLink(Request $request)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'destination' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $method = $data['method'];
        $destination = $this->normalizeDestination($method, $data['destination']);
        $code = $data['code'];

        try {
            $this->performVerification($request, $method, $destination, $code);
        } catch (AuthenticationException $exception) {
            return $this->verificationView(false, $exception->getMessage(), 401);
        } catch (\RuntimeException $exception) {
            return $this->verificationView(false, $exception->getMessage(), 404);
        } catch (ValidationException $exception) {
            return $this->verificationView(false, 'Código inválido ou expirado.', 422);
        } catch (\Throwable $exception) {
            Log::error('Failed to verify MFA code', [
                'method' => $method,
                'destination' => $destination,
                'error' => $exception->getMessage(),
            ]);

            return $this->verificationView(false, 'Não foi possível verificar o código.', 500);
        }

        return $this->verificationView(true, 'Método verificado com sucesso.');
    }

    private function resolveUserForMethod(Request $request, string $method, string $destination): User
    {
        if ($method === 'email') {
            $user = User::query()->where('email', $destination)->first();

            if (!$user) {
                throw new \RuntimeException('Usuário não encontrado para este e-mail.');
            }

            return $user;
        }

        $user = $request->user() ?? Auth::guard('api')->user();

        if (!$user) {
            throw new AuthenticationException('Autenticação obrigatória para este método.');
        }

        return $user;
    }

    private function normalizeDestination(string $method, string $destination): string
    {
        $normalized = trim($destination);

        if ($method === 'email') {
            return strtolower($normalized);
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';

        if ($digits === '') {
            throw ValidationException::withMessages([
                'destination' => ['Destino inválido.'],
            ]);
        }

        return $digits;
    }

    private function activateUser(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
        ])->save();
    }

    private function performVerification(Request $request, string $method, string $destination, string $code): MfaMethod
    {
        $user = $this->resolveUserForMethod($request, $method, $destination);

        $methodModel = $this->mfaService->verifyCode($user, $method, $destination, $code);

        if ($method === 'email') {
            $this->activateUser($user);
        }

        return $methodModel;
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
