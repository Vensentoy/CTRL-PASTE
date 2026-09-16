<?php

namespace Tests\Feature\Workflows;

/**
 * Click-through of the auth layer (workflows.md §0 + Session & auth
 * notes in roles-and-permissions.md). These pin the spec's intended
 * shape: login is username-based, there is deliberately no self-service
 * register / password-reset (no email on schema), and roles forward to
 * their own dashboards.
 */
class LoginAndOnboardingTest extends WorkflowTestCase
{
    public function test_login_uses_username_and_forwards_to_dashboard(): void
    {
        $coordinator = $this->makeCoordinator('coord.reyes', 'Reyes');
        $student = $this->makeStudent($coordinator, 'juan.delacruz');

        $this->post('/login', [
            'username' => 'juan.delacruz',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_coordinator_login_forwards_to_coordinator_dashboard(): void
    {
        $coordinator = $this->makeCoordinator('coord.reyes', 'Reyes');

        $this->post('/login', [
            'username' => 'coord.reyes',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('coordinator.dashboard'));
    }

    public function test_register_and_forgot_password_routes_are_intentionally_absent(): void
    {
        // Spec: only Student and OJT Coordinator accounts exist (roles-
        // and-permissions.md) and username-only schema has no email — no
        // self-service registration or password recovery.
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_guest_cannot_reach_student_or_coordinator_routes(): void
    {
        $this->get(route('student.dar.index'))->assertRedirect('/login');
        $this->get(route('coordinator.students.index'))->assertRedirect('/login');
    }

    public function test_role_gate_blocks_student_from_coordinator_routes(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.rolecheck');

        $this->actingAs($student->user)
            ->get(route('coordinator.dashboard'))
            ->assertForbidden();

        $this->actingAs($student->user)
            ->get(route('coordinator.students.index'))
            ->assertForbidden();
    }

    public function test_role_gate_blocks_coordinator_from_student_routes(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.rolecheck');

        $this->actingAs($coordinator->user)
            ->get(route('student.dar.index'))
            ->assertForbidden();
    }
}