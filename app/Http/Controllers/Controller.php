<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="MS Auth API",
 *     version="1.0.0",
 *     description="Serviços de autenticação, registro e MFA utilizados pelos clientes da plataforma."
 * )
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Gateway principal"
 * )
 * @OA\Tag(
 *     name="Auth",
 *     description="Fluxos de autenticação e gerenciamento de sessão."
 * )
 * @OA\Tag(
 *     name="MFA",
 *     description="Operações relacionadas à verificação em duplo fator."
 * )
 */
abstract class Controller
{
    //
}
