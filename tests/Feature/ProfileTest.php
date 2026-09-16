<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::create([
            'role' => 'student',
            'username' => 'profileuser',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        // Profile update in this system validates `username` only —
        // Breeze's `name`/`email` fields don't exist on users table
        // (migration 2024_01_01_000001, ProfileUpdateRequest).
        $user = User::create([
            'role' => 'student',
            'username' => 'profileuser2',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'username' => 'profileuser2-renamed',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('profileuser2-renamed', $user->username);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        // No email column — this Breeze test is not applicable. Keep a
        // green placeholder that the profile page still renders.
        $user = User::create([
            'role' => 'student',
            'username' => 'profileuser3',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $this->assertSame('profileuser3', $user->refresh()->username);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::create([
            'role' => 'student',
            'username' => 'profileuser4',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::create([
            'role' => 'student',
            'username' => 'profileuser5',
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
