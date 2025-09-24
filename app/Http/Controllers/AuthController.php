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
            Log::error('Erro no registro de usuário: ' . $e->getMessage(), [
                'email' => $data['email'] ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erro interno do servidor',
                'debug' => config('app.debug') ? $e->getMessage() : 'Verifique os logs para mais detalhes'
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

    // Refresh Token (opcional)
    public function refresh(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'token' => $token,
        ]);
    }
}
