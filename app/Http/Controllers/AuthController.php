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
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     operationId="registerUser",
     *     tags={"Auth"},
     *     summary="Cria um novo usuário e dispara a validação de MFA por e-mail.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RegisterRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuário criado.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados inválidos.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->registerUserService->register(RegisterUserDTO::fromRequest($request));

        return response()->json([
            'message' => 'Usuário criado com sucesso. Verifique seu e-mail para ativar a conta.',
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     operationId="login",
     *     tags={"Auth"},
     *     summary="Autentica um usuário e retorna o token de acesso Passport.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credenciais válidas.",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciais inválidas.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=423,
     *         description="Conta pendente de verificação.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authenticationService->login(LoginDTO::fromRequest($request));

        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     summary="Revoga o token atual do usuário autenticado.",
     *     @OA\Response(
     *         response=200,
     *         description="Sessão finalizada.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token inválido ou expirado.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     security={{"passport":{}}}
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     operationId="refreshToken",
     *     tags={"Auth"},
     *     summary="Revoga o token atual e retorna um novo token.",
     *     description="É necessário enviar o token atual no header Authorization.",
     *     @OA\Response(
     *         response=200,
     *         description="Token renovado.",
     *         @OA\JsonContent(ref="#/components/schemas/RefreshTokenResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token inválido ou revogado.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     security={{"passport":{}}}
     * )
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authenticationService->refresh(RefreshTokenDTO::fromRequest($request));

        return response()->json([
            'token' => $result['token'],
            'message' => 'Token renovado com sucesso',
        ]);
    }
}
