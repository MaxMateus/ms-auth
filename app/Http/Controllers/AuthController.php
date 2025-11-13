<?php

namespace App\Http\Controllers;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RefreshTokenDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthenticationService;
use App\Services\RegisterUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->registerUserService->register(RegisterUserDTO::fromRequest($request));

        return response()->json([
            'message' => 'Usuário criado com sucesso. Verifique seu e-mail para ativar a conta.',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authenticationService->login(LoginDTO::fromRequest($request));

        return response()->json($result);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authenticationService->refresh(RefreshTokenDTO::fromRequest($request));

        return response()->json([
            'token' => $result['token'],
            'message' => 'Token renovado com sucesso',
        ]);
    }
}
