<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\MfaCode;
use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_creates_pending_user_and_creates_email_mfa_code(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJson([
                'message' => 'Usuário criado com sucesso. Verifique seu e-mail para ativar a conta.',
            ]);

        $user = User::query()->where('email', 'max@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::PendingVerification->value,
        ]);

        $this->assertDatabaseHas('mfa_methods', [
            'user_id' => $user->id,
            'method' => 'email',
            'verified' => false,
        ]);

        $this->assertDatabaseHas('mfa_codes', [
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => 'max@example.com',
            'used' => false,
        ]);
    }

    public function test_registration_with_existing_email_returns_conflict(): void
    {
        User::factory()->create([
            'email' => 'dup@example.com',
            'cpf' => '98765432100',
        ]);

        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'email' => 'dup@example.com',
            'cpf' => '987.654.321-00',
        ]));

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Usuário já cadastrado no sistema.',
            ])
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_email_verification_activates_user_and_registers_mfa_method(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'verify@example.com',
            'password' => Hash::make('12345678'),
            'cpf' => '55566677788',
        ]);

        MfaMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'verified' => false,
        ]);

        $code = '123456';

        MfaCode::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'code' => $code,
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/mfa/verify', [
            'method' => 'email',
            'destination' => $user->email,
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Método verificado com sucesso.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Active->value,
        ]);

        $this->assertDatabaseHas('mfa_methods', [
            'user_id' => $user->id,
            'method' => 'email',
            'verified' => true,
        ]);
        $this->assertDatabaseHas('mfa_codes', [
            'user_id' => $user->id,
            'code' => $code,
            'used' => true,
        ]);
    }

    public function test_verification_with_invalid_code_returns_error(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'invalid@example.com',
        ]);

        MfaMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'verified' => false,
        ]);

        $response = $this->postJson('/api/mfa/verify', [
            'method' => 'email',
            'destination' => $user->email,
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Código inválido ou expirado.',
            ]);
    }

    public function test_verification_with_expired_token_returns_unprocessable_entity(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'expired@example.com',
        ]);

        MfaMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'verified' => false,
        ]);

        MfaCode::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'code' => '999999',
            'used' => false,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/mfa/verify', [
            'method' => 'email',
            'destination' => $user->email,
            'code' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Código inválido ou expirado.',
            ]);
    }

    public function test_email_can_be_verified_via_link(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'link@example.com',
            'password' => Hash::make('12345678'),
            'cpf' => '22233344455',
        ]);

        MfaMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'verified' => false,
        ]);

        $code = '654321';

        MfaCode::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'code' => $code,
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->get('/api/mfa/verify-link?' . http_build_query([
            'method' => 'email',
            'destination' => $user->email,
            'code' => $code,
        ]));

        $response->assertOk()
            ->assertSee('Método verificado com sucesso.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Active->value,
        ]);

        $this->assertDatabaseHas('mfa_methods', [
            'user_id' => $user->id,
            'method' => 'email',
            'verified' => true,
        ]);

        $this->assertDatabaseHas('mfa_codes', [
            'user_id' => $user->id,
            'code' => $code,
            'used' => true,
        ]);
    }

    public function test_login_is_blocked_until_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('password123'),
            'cpf' => '11122233344',
        ]);

        MfaMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'email',
            'destination' => $user->email,
            'verified' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Conta ainda não ativada. Verifique seu e-mail antes de fazer login.'
            ]);
    }

    public function test_registration_fails_with_invalid_cpf(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'cpf' => '111.111.111-11',
        ]));

        $response->assertStatus(422)
            ->assertJson([
            'message' => 'O CPF informado não é válido.',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $base = [
            'name' => 'Max Mateus',
            'email' => 'max@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'cpf' => '123.456.789-09',
            'phone' => '(11) 91234-5678',
            'birthdate' => '1990-01-01',
            'gender' => 'M',
            'accept_terms' => true,
            'street' => 'Rua Exemplo',
            'number' => '123',
            'complement' => 'Apto 1',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'sp',
            'zip_code' => '12345-678',
        ];

        return array_merge($base, $overrides);
    }
}
