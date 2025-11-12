<?php

namespace App\Http\Controllers;

use App\DTOs\RegisterUserDTO;
use App\Enums\UserStatus;
use App\Exceptions\InvalidCpfException;
use App\Exceptions\UserAlreadyExistsException;
use App\Http\Requests\RegisterRequest;
use App\Models\MfaMethod;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\RegisterUserService;
use App\Services\TokenCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Token;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly TokenCacheService $tokenCacheService,
    ) {
    }

    public function register(RegisterRequest $request)
    {
        try {
            $this->registerUserService->register(RegisterUserDTO::fromRequest($request));

            return response()->json([
                'message' => 'Usuário criado com sucesso. Verifique seu e-mail para ativar a conta.'
            ], 201);
        } catch (UserAlreadyExistsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->conflicts(),
            ], 409);
        } catch (InvalidCpfException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Erro no registro de usuário', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }

    // Login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);
        
        if (!Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }
        
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->status !== UserStatus::Active || !$user->email_verified_at) {
            Auth::logout();

            return response()->json([
                'message' => 'Conta ainda não ativada. Verifique seu e-mail antes de fazer login.'
            ], 403);
        }

        $tokenResult = $user->createToken('authToken');
        $token = $tokenResult->accessToken;

        $this->tokenCacheService->store($tokenResult->token, [
            'user_id' => $user->getKey(),
            'client_id' => $tokenResult->token->client_id,
            'scopes' => $tokenResult->token->scopes,
        ]);

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'error' => 'Invalid or expired token.',
                ], 401);
            }

            $token = $user->token();

            if (!$token) {
                return response()->json([
                    'error' => 'Token not found or already revoked.',
                ], 400);
            }

            $this->tokenCacheService->forget($token->id);
            $token->revoke();

            return response()->json([
                'message' => 'Logged out successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred during logout.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string']
        ]);

        $token = $request->query('token');
        $payload = $this->emailVerificationService->getPayload($token);

        if (!$payload) {
            return response()->json([
                'message' => 'Token inválido.'
            ], 400);
        }

        if ($this->emailVerificationService->tokenExpired($payload)) {
            $this->emailVerificationService->delete($token);

            return response()->json([
                'message' => 'Token expirado.'
            ], 422);
        }

        $user = User::find($payload['user_id'] ?? null);

        if (!$user || $user->email !== ($payload['email'] ?? null)) {
            $this->emailVerificationService->delete($token);

            return response()->json([
                'message' => 'Token inválido.'
            ], 400);
        }

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
            ])->save();

            MfaMethod::updateOrCreate(
                ['user_id' => $user->id, 'method' => 'email'],
                ['destination' => $user->email, 'verified' => true]
            );
        });

        $this->emailVerificationService->delete($token);

        return response()->json([
            'message' => 'E-mail confirmado com sucesso. Sua conta está ativa.'
        ]);
    }

    // Refresh Token
    public function refresh(Request $request)
    {
        try {
            // Verifica se o header Authorization está presente
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'message' => 'Token não fornecido.',
                    'error' => 'token_missing'
                ], 400);
            }

            $jti = $this->extractTokenId($authHeader);
            if (!$jti) {
                return response()->json([
                    'message' => 'Token malformado.',
                    'error' => 'malformed_token'
                ], 400);
            }

            $cachedToken = $this->tokenCacheService->get($jti);
            $userId = $cachedToken['user_id'] ?? null;

            $passportToken = null;

            if ($cachedToken) {
                $tokenIsValid = Token::query()
                    ->where('id', $jti)
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())
                    ->exists();

                if (!$tokenIsValid) {
                    $this->tokenCacheService->forget($jti);

                    return response()->json([
                        'message' => 'Token inválido ou expirado.',
                        'error' => 'invalid_token'
                    ], 400);
                }
            } else {
                $passportToken = Token::query()
                    ->where('id', $jti)
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())
                    ->first();

                if (!$passportToken) {
                    return response()->json([
                        'message' => 'Token inválido ou expirado.',
                        'error' => 'invalid_token'
                    ], 400);
                }

                $userId = $passportToken->user_id;

                $this->tokenCacheService->store($passportToken, [
                    'user_id' => $passportToken->user_id,
                    'client_id' => $passportToken->client_id,
                    'scopes' => $passportToken->scopes,
                ]);
            }

            // Busca o usuário
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                    'error' => 'user_not_found'
                ], 400);
            }

            // Revoga o token atual
            DB::table('oauth_access_tokens')
                ->where('id', $jti)
                ->update(['revoked' => true]);
            $this->tokenCacheService->forget($jti);

            // Cria um novo token
            $newTokenResult = $user->createToken('authToken');
            $newToken = $newTokenResult->accessToken;

            $this->tokenCacheService->store($newTokenResult->token, [
                'user_id' => $user->getKey(),
                'client_id' => $newTokenResult->token->client_id,
                'scopes' => $newTokenResult->token->scopes,
            ]);

            return response()->json([
                'token' => $newToken,
                'message' => 'Token renovado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            // Log do erro para debug
            Log::error('Erro no refresh de token: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Token inválido ou expirado.',
                'error' => 'invalid_token'
            ], 400);
        }
    }

    private function extractTokenId(string $authHeader): ?string
    {
        $tokenValue = substr($authHeader, 7);

        try {
            $tokenParts = explode('.', $tokenValue);
            if (count($tokenParts) !== 3) {
                throw new \Exception('Token malformado');
            }

            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            if (!$payload || !isset($payload['jti'])) {
                throw new \Exception('Payload inválido');
            }

            return $payload['jti'];
        } catch (\Exception $e) {
            Log::warning('Falha ao extrair JTI do token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

}
