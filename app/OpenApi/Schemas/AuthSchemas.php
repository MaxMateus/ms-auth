<?php

namespace App\OpenApi\Schemas;

use OpenApi\Annotations as OA;

/**
 * Coleção de esquemas reutilizados pela documentação OpenAPI.
 *
 * @OA\Schema(
 *     schema="RegisterRequest",
 *     required={
 *         "name","email","password","password_confirmation","cpf","phone","birthdate",
 *         "gender","accept_terms","street","number","neighborhood","city","state","zip_code"
 *     },
 *     @OA\Property(property="name", type="string", example="Maria Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="Str0ngPass!"),
 *     @OA\Property(property="password_confirmation", type="string", format="password", example="Str0ngPass!"),
 *     @OA\Property(property="cpf", type="string", example="123.456.789-10"),
 *     @OA\Property(property="phone", type="string", example="+55 11 99999-9999"),
 *     @OA\Property(property="birthdate", type="string", format="date", example="1985-06-20"),
 *     @OA\Property(property="gender", type="string", enum={"M","F","Outro"}, example="F"),
 *     @OA\Property(property="accept_terms", type="boolean", example=true),
 *     @OA\Property(property="street", type="string", example="Av. Paulista"),
 *     @OA\Property(property="number", type="string", example="1000"),
 *     @OA\Property(property="complement", type="string", nullable=true, example="Apto 12"),
 *     @OA\Property(property="neighborhood", type="string", example="Bela Vista"),
 *     @OA\Property(property="city", type="string", example="São Paulo"),
 *     @OA\Property(property="state", type="string", example="SP"),
 *     @OA\Property(property="zip_code", type="string", example="01310-100")
 * )
 *
 * @OA\Schema(
 *     schema="LoginRequest",
 *     required={"email","password"},
 *     @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="Str0ngPass!")
 * )
 *
 * @OA\Schema(
 *     schema="LoginResponse",
 *     required={"token","user"},
 *     @OA\Property(property="token", type="string", example="ey..."),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 *
 * @OA\Schema(
 *     schema="RefreshTokenResponse",
 *     required={"token","message"},
 *     @OA\Property(property="token", type="string", example="ey..."),
 *     @OA\Property(property="message", type="string", example="Token renovado com sucesso")
 * )
 *
 * @OA\Schema(
 *     schema="MessageResponse",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Operação executada com sucesso.")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     required={"id","name","email","cpf","phone","birthdate","gender","accept_terms","status"},
 *     @OA\Property(property="id", type="integer", example=10),
 *     @OA\Property(property="name", type="string", example="Maria Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *     @OA\Property(property="cpf", type="string", example="123.456.789-10"),
 *     @OA\Property(property="phone", type="string", example="+55 11 99999-9999"),
 *     @OA\Property(property="birthdate", type="string", format="date", example="1985-06-20"),
 *     @OA\Property(property="gender", type="string", enum={"M","F","Outro"}),
 *     @OA\Property(property="accept_terms", type="boolean", example=true),
 *     @OA\Property(property="street", type="string"),
 *     @OA\Property(property="number", type="string"),
 *     @OA\Property(property="complement", type="string", nullable=true),
 *     @OA\Property(property="neighborhood", type="string"),
 *     @OA\Property(property="city", type="string"),
 *     @OA\Property(property="state", type="string", example="SP"),
 *     @OA\Property(property="zip_code", type="string", example="01310-100"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MfaMethod",
 *     required={"id","method","destination","verified"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="method", type="string", enum={"email","sms","whatsapp"}),
 *     @OA\Property(property="destination", type="string", example="maria@example.com"),
 *     @OA\Property(property="verified", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="MfaMethodsResponse",
 *     required={"methods"},
 *     @OA\Property(
 *         property="methods",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MfaMethod")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MfaSendRequest",
 *     required={"method","destination"},
 *     @OA\Property(property="method", type="string", enum={"email","sms","whatsapp"}),
 *     @OA\Property(property="destination", type="string", example="maria@example.com")
 * )
 *
 * @OA\Schema(
 *     schema="MfaVerifyRequest",
 *     required={"method","destination","code"},
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MfaSendRequest"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="code", type="string", example="123456")
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MfaVerifyResponse",
 *     required={"message","method"},
 *     @OA\Property(property="message", type="string", example="Método verificado com sucesso."),
 *     @OA\Property(property="method", ref="#/components/schemas/MfaMethod")
 * )
 */
class AuthSchemas
{
}
