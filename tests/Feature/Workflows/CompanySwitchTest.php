<?php

namespace Tests\Feature\Workflows;

use App\Models\CompanyAssignment;

/**
 * BR-12 — Company Assignments are historized, never overwritten in
 * place. "Switching" companies closes the active row (end_date) and
 * inserts a fresh row inside one DB transaction. The same form also
 * covers the very first assignment (no active row to close).
 */
class CompanySwitchTest extends WorkflowTestCase
{
    public function test_first_assignment_creates_one_active_row(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.company');

        $this->actingAs($student->user)
            ->post(route('student.company.store'), [
                'company_name' => 'ACME Corp',
                'department_area' => 'IT',
                'job_designation' => 'IT Intern',
                'start_date' => now()->subWeek()->toDateString(),
            ])
            ->assertRedirect(route('student.company.index'));

        $this->assertDatabaseCount('company_assignments', 1);
        $this->assertSame('ACME Corp', $student->activeCompanyAssignment()->company_name);
        $this->assertNull($student->activeCompanyAssignment()->end_date);
    }

    public function test_switch_closes_old_row_and_inserts_new_one(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.company');

        $oldStart = now()->subWeeks(2)->toDateString();
        CompanyAssignment::create([
            'student_id' => $student->id,
            'company_name' => 'ACME Corp',
            'start_date' => $oldStart,
        ]);

        $newStart = now()->addWeek()->toDateString();

        $this->actingAs($student->user)
            ->post(route('student.company.store'), [
                'company_name' => 'Globex Inc',
                'department_area' => 'QA',
                'job_designation' => 'QA Intern',
                'start_date' => $newStart,
            ])
            ->assertRedirect(route('student.company.index'));

        $this->assertDatabaseCount('company_assignments', 2);

        // Old row closed on the new start date (same-day cutover), never
        // overwritten in place (BR-12).
        $old = $student->companyAssignments()->where('company_name', 'ACME Corp')->first();
        $this->assertSame($newStart, $old->end_date->toDateString());
        $this->assertSame($oldStart, $old->start_date->toDateString());

        // Exactly one active row now — the new one.
        $active = $student->activeCompanyAssignment();
        $this->assertSame('Globex Inc', $active->company_name);
        $this->assertNull($active->end_date);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Update']);
    }

    public function test_new_start_date_cannot_predate_the_active_assignment(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.company');

        $activeStart = now()->subWeek()->toDateString();
        CompanyAssignment::create([
            'student_id' => $student->id,
            'company_name' => 'ACME Corp',
            'start_date' => $activeStart,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.company.store'), [
                'company_name' => 'Globex Inc',
                'start_date' => now()->subMonths(2)->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('company_assignments', 1);
    }

    public function test_coordinator_can_see_the_full_company_history(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.company');

        CompanyAssignment::create([
            'student_id' => $student->id,
            'company_name' => 'ACME Corp',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ]);
        CompanyAssignment::create([
            'student_id' => $student->id,
            'company_name' => 'Globex Inc',
            'start_date' => now()->subWeek()->toDateString(),
        ]);

        $this->actingAs($coordinator->user)
            ->get(route('coordinator.students.show', $student))
            ->assertOk()
            ->assertSee('ACME Corp')
            ->assertSee('Globex Inc');
    }
}