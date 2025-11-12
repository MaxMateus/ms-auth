<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegisterVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_creates_pending_user_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJson([
                'message' => 'Usuário criado com sucesso. Verifique seu e-mail para ativar a conta.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'max@example.com',
            'status' => UserStatus::PendingVerification->value,
            'cpf' => '12345678909',
        ]);

        Queue::assertPushed(SendVerificationEmailJob::class);
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
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'verify@example.com',
            'password' => Hash::make('12345678'),
            'cpf' => '55566677788',
        ]);

        /** @var EmailVerificationService $service */
        $service = app(EmailVerificationService::class);
        $token = $service->createToken($user);

        $response = $this->getJson('/api/auth/verify-email?token=' . $token);

        $response->assertOk()
            ->assertJson([
                'message' => 'E-mail confirmado com sucesso. Sua conta está ativa.'
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
    }

    public function test_verification_with_invalid_token_returns_error(): void
    {
        $response = $this->getJson('/api/auth/verify-email?token=invalid-token');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Token inválido.'
            ]);
    }

    public function test_verification_with_expired_token_returns_unprocessable_entity(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create([
            'email' => 'expired@example.com',
        ]);

        /** @var EmailVerificationService $service */
        $service = app(EmailVerificationService::class);
        $token = $service->createToken($user);

        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => now()->subMinute()->toIso8601String(),
        ];

        Cache::store(config('email_verification.store'))
            ->put(config('email_verification.key_prefix') . $token, $payload, 60);

        $response = $this->getJson('/api/auth/verify-email?token=' . $token);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Token expirado.'
            ]);
    }

    public function test_login_is_blocked_until_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('password123'),
            'cpf' => '11122233344',
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
