<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_endpoint_handles_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/verify-email?token=invalid');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Token inválido.'
            ]);
    }
}
