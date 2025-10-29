<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\RegisterRequest;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Token;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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
        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Logged out successfully',
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

            // Extrai o token JWT
            $tokenValue = substr($authHeader, 7);
            
            // Decodifica o JWT para obter o jti (JWT ID)
            try {
                $tokenParts = explode('.', $tokenValue);
                if (count($tokenParts) !== 3) {
                    throw new \Exception('Token malformado');
                }
                
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                if (!$payload || !isset($payload['jti'])) {
                    throw new \Exception('Payload inválido');
                }
                
                $jti = $payload['jti'];
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Token malformado.',
                    'error' => 'malformed_token'
                ], 400);
            }
            
            // Busca o token no banco usando o JTI
            $token = DB::table('oauth_access_tokens')
                      ->where('id', $jti)
                      ->where('revoked', false)
                      ->where('expires_at', '>', now())
                      ->first();
            
            if (!$token) {
                return response()->json([
                    'message' => 'Token inválido ou expirado.',
                    'error' => 'invalid_token'
                ], 400);
            }

            // Busca o usuário
            $user = User::find($token->user_id);
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
            
            // Cria um novo token
            $newToken = $user->createToken('authToken')->accessToken;

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
}
