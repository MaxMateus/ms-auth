<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\RegisterRequest;
use App\Services\UserService;
use App\Services\TokenCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

class AuthController extends Controller
{
    protected UserService $userService;
    protected TokenCacheService $tokenCacheService;

    public function __construct(UserService $userService, TokenCacheService $tokenCacheService)
    {
        $this->userService = $userService;
        $this->tokenCacheService = $tokenCacheService;
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        // Verificar se usuário já existe
        $existingUserCheck = $this->userService->checkUserExists($data['email'], $data['cpf']);
        
        if ($existingUserCheck['exists']) {
            return response()->json([
                'message' => 'Usuário já cadastrado no sistema.',
                'errors' => $existingUserCheck['conflicts']
            ], 400);
        }

        if (!$this->userService->validateCpfFormat($data['cpf'])) {
            return response()->json([
                'message' => 'O CPF informado não é válido.',
            ], 422);
        }

        try {
            $user = $this->userService->createUser($data);

            return response()->json([
                'message' => 'Usuário registrado com sucesso',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'cpf' => $user->cpf,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            // Log do erro para debug
            Log::error('Erro no registro de usuário: ' . $e->getMessage());

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
