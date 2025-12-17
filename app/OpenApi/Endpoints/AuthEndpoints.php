<?php

namespace App\OpenApi\Endpoints;

use OpenApi\Annotations as OA;

/**
 * @OA\Get(
 *     path="/me",
 *     operationId="currentUser",
 *     tags={"Auth"},
 *     summary="Retorna os dados do usuário autenticado.",
 *     @OA\Response(
 *         response=200,
 *         description="Usuário autenticado.",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Token inválido.",
 *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
 *     ),
 *     security={{"passport":{}}}
 * )
 */
class AuthEndpoints
{
}
