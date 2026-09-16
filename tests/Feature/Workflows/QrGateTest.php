<?php

namespace Tests\Feature\Workflows;

use App\Models\QrAccessToken;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * QR-gated access: only via coordinator-generated single-use signed QR.
 */
class QrGateTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Enforce gate in this test class (otherwise testing env bypasses).
        config(['qr.bypass_in_testing' => false]);
    }

    public function test_direct_login_without_qr_is_blocked(): void
    {
        $this->get('/login')->assertStatus(403)->assertSee('Scan the QR');
        $this->post('/login', ['username' => 'x', 'password' => 'y'])->assertStatus(403);
    }

    public function test_qr_enter_with_valid_token_sets_session_and_unblocks_login(): void
    {
        $plain = Str::random(64);
        $expiresAt = now()->addMinutes(2);
        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
            'max_uses' => 1,
            'used_count' => 0,
        ]);
        $signed = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain]);

        $this->get($signed)->assertRedirect(route('login'));

        // Now login page shows form (gate passed via session).
        $this->get('/login')->assertOk()->assertSee('Username');
    }

    public function test_expired_token_returns_gone(): void
    {
        $plain = Str::random(64);
        $expiresAt = now()->subMinute(); // already expired in DB
        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
            'max_uses' => 1,
            'used_count' => 0,
        ]);
        // Signed route must still be valid (future) to get past signature check, but DB says expired.
        $signed = URL::temporarySignedRoute('qr.enter', now()->addMinutes(2), ['token' => $plain]);

        $this->get($signed)->assertStatus(410)->assertSee('expired');
    }

    public function test_single_use_token_second_scan_is_gone(): void
    {
        $plain = Str::random(64);
        $expiresAt = now()->addMinutes(2);
        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
            'max_uses' => 1,
            'used_count' => 0,
        ]);
        $signed = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain]);

        $this->get($signed)->assertRedirect(route('login'));
        $this->get($signed)->assertStatus(410);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $plain = Str::random(64);
        $expiresAt = now()->addMinutes(2);
        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);
        $signed = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain]);
        // Tamper token param -> signature invalid
        $tampered = $signed . 'x';

        $this->get($tampered)->assertRedirect(route('login'))->assertSessionHasErrors('qr');
    }

    public function test_login_succeeds_after_qr_session(): void
    {
        $coordinator = $this->makeCoordinator('coord.qr', 'Coord QR');
        $student = $this->makeStudent($coordinator, 'student.qr');

        $plain = Str::random(64);
        $expiresAt = now()->addMinutes(2);
        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);
        $signed = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain]);
        $this->get($signed)->assertRedirect(route('login'));

        $this->post('/login', [
            'username' => $student->user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($student->user);
    }

    public function test_coordinator_can_generate_qr(): void
    {
        // In testing we keep bypass true for this one check of generation endpoint
        config(['qr.bypass_in_testing' => true]);
        $coordinator = $this->makeCoordinator('coord.gen', 'Gen Coord');
        $this->actingAs($coordinator->user)
            ->post(route('coordinator.qr.generate'))
            ->assertOk()
            ->assertJsonStructure(['qr_data_url', 'signed_url', 'expires_at', 'ttl_seconds']);

        $this->assertDatabaseCount('qr_access_tokens', 1);
    }

    public function test_student_cannot_generate_qr(): void
    {
        config(['qr.bypass_in_testing' => true]);
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.nogen');
        $this->actingAs($student->user)
            ->post(route('coordinator.qr.generate'))
            ->assertStatus(403);
    }

    public function test_coordinator_can_login_directly_without_qr_to_generate(): void
    {
        $coordinator = $this->makeCoordinator('coord.direct', 'Direct Coord');
        // No QR session — coordinator login should still pass via bypass.
        $this->post('/login', [
            'username' => $coordinator->user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($coordinator->user);

        // Student direct login without QR must still be blocked.
        $student = $this->makeStudent($coordinator, 'student.blocked2');
        $this->post('/logout');
        $this->post('/login', [
            'username' => $student->user->username,
            'password' => 'password',
        ])->assertStatus(403);
        $this->assertGuest();
    }
}
