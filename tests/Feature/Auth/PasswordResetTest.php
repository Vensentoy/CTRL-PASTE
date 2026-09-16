<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        // Self-service password reset is intentionally disabled — no email
        // column, username-based login (roles-and-permissions.md). The
        // coordinator-assisted reset at `coordinator.students.reset-password`
        // is the supported flow (tested in Workflows/CompletionAndPdfTest).
        $response = $this->get('/forgot-password');

        $response->assertStatus(404);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'test@example.com']);

        $response->assertStatus(404);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/reset-password/fake-token');

        $response->assertStatus(404);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $response = $this->post('/reset-password', [
            'token' => 'fake-token',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }
}
