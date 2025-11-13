<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_send_returns_not_found_for_unknown_email(): void
    {
        $response = $this->postJson('/api/mfa/send', [
            'method' => 'email',
            'destination' => 'unknown@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Usuário não encontrado para este e-mail.'
            ]);
    }
}
