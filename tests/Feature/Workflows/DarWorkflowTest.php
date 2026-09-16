<?php

namespace Tests\Feature\Workflows;

use App\Services\CompletedHoursRecalculator;

/**
 * workflows.md §2 (Daily Logging) + §3 (batch submit + late flag).
 * Pins BR-3 (hours derived server-side), BR-4 (date bounds), BR-6
 * (lateness vs cycle deadline only), BR-7 (per-row transitions), BR-9
 * (soft-delete only), BR-10 (Completion lock), BR-11/BR-14 (isolation).
 */
class DarWorkflowTest extends WorkflowTestCase
{
    public function test_store_creates_a_draft_with_hours_derived_server_side(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Assisted in network setup.',
                'time_started' => '08:00',
                'time_ended' => '12:30',
            ])
            ->assertRedirect(route('student.dar.index'));

        $dar = $student->dailyAccomplishmentReports()->first();

        $this->assertNotNull($dar);
        $this->assertSame('Draft', $dar->status);
        $this->assertNull($dar->cycle_id);
        // BR-3: 08:00 -> 12:30 is 4.5 hours, computed by the model, never
        // accepted from the request.
        $this->assertEquals(4.5, (float) $dar->hours_rendered);
    }

    public function test_report_date_cannot_be_in_the_future(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->addDay()->toDateString(),
                'activities_text' => 'Future entry.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertSessionHasErrors('report_date');
    }

    public function test_report_date_stays_inside_the_students_ojt_window(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        // BR-4: before ojt_start_date.
        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->subWeeks(3)->toDateString(),
                'activities_text' => 'Too early.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertSessionHasErrors('report_date');

        // BR-4: after ojt_completion_date.
        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->addMonths(4)->toDateString(),
                'activities_text' => 'Too late.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertSessionHasErrors('report_date');
    }

    public function test_time_ended_must_be_after_time_started(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Backwards clock.',
                'time_started' => '12:00',
                'time_ended' => '08:00',
            ])
            ->assertSessionHasErrors('time_ended');
    }

    public function test_batch_submit_moves_drafts_into_an_open_cycle(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');
        $cycle = $this->makeCycle($coordinator);

        $darA = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $darB = $this->makeDar($student, ['report_date' => now()->subDay()->toDateString()]);

        $this->actingAs($student->user)
            ->post(route('student.dar.submit'), [
                'cycle_id' => $cycle->id,
                'dar_ids' => [$darA->id, $darB->id],
            ])
            ->assertRedirect(route('student.dar.index'));

        $this->assertSame('Pending', $darA->fresh()->status);
        $this->assertSame($cycle->id, $darA->fresh()->cycle_id);
        $this->assertSame('Pending', $darB->fresh()->status);

        // submit() logs one audit row per batch click.
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Submit']);
    }

    public function test_late_flag_depends_only_on_the_cycle_deadline(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        // BR-6: submitted AFTER the deadline -> Late, even though the
        // activity date itself is recent and inside the coverage window.
        $lateCycle = $this->makeCycle($coordinator, [
            'deadline_date' => now()->subDay()->toDateString(),
        ]);
        $dar = $this->makeDar($student, ['report_date' => now()->subDays(2)->toDateString()]);

        $this->actingAs($student->user)
            ->post(route('student.dar.submit'), [
                'cycle_id' => $lateCycle->id,
                'dar_ids' => [$dar->id],
            ]);

        $this->assertSame('Late', $dar->fresh()->status);
    }

    public function test_drafts_are_invisible_to_the_coordinator_until_submitted(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');
        $cycle = $this->makeCycle($coordinator);

        $submitted = $this->makeDar($student, ['activities_text' => 'SUBMITTED ACTIVITY TEXT']);
        $draftOnly = $this->makeDar($student, ['activities_text' => 'STILL A DRAFT TEXT']);

        $this->actingAs($student->user)
            ->post(route('student.dar.submit'), [
                'cycle_id' => $cycle->id,
                'dar_ids' => [$submitted->id],
            ]);

        // Draft keeps cycle_id null.
        $this->assertNull($draftOnly->fresh()->cycle_id);

        $this->actingAs($coordinator->user)
            ->get(route('coordinator.dar.review', $cycle))
            ->assertOk()
            ->assertSee('SUBMITTED ACTIVITY TEXT')
            ->assertDontSee('STILL A DRAFT TEXT');
    }

    public function test_student_cannot_submit_into_another_coordinators_cycle(): void
    {
        $coordinatorA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordinatorB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordinatorA, 'student.dar');
        $cycleOfB = $this->makeCycle($coordinatorB);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);

        $this->actingAs($student->user)
            ->post(route('student.dar.submit'), [
                'cycle_id' => $cycleOfB->id,
                'dar_ids' => [$dar->id],
            ])
            ->assertSessionHasErrors('cycle_id');

        $this->assertNull($dar->fresh()->cycle_id);
    }

    public function test_student_cannot_edit_another_students_dar(): void
    {
        $coordinator = $this->makeCoordinator();
        $owner = $this->makeStudent($coordinator, 'student.owner');
        $intruder = $this->makeStudent($coordinator, 'student.intruder');

        $dar = $this->makeDar($owner, ['report_date' => now()->toDateString()]);

        $this->actingAs($intruder->user)
            ->get(route('student.dar.edit', $dar))
            ->assertForbidden();

        $this->actingAs($intruder->user)
            ->put(route('student.dar.update', $dar), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Hijacked.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_edit_a_submitted_dar(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');
        $cycle = $this->makeCycle($coordinator);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($student->user)
            ->put(route('student.dar.update', $dar), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Editing after submit.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertForbidden();
    }

    public function test_draft_delete_is_a_soft_delete(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar');

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);

        $this->actingAs($student->user)
            ->delete(route('student.dar.destroy', $dar))
            ->assertRedirect(route('student.dar.index'));

        $this->assertSoftDeleted('daily_accomplishment_reports', ['id' => $dar->id]);
    }

    public function test_completed_student_is_locked_from_new_drafts(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.dar', ['required_hours' => 8]);

        $approved = $this->makeDar($student, [
            'report_date' => now()->toDateString(),
            'status' => 'Approved',
            'time_started' => '08:00',
            'time_ended' => '16:00', // 8h — meets required_hours
        ]);

        (new CompletedHoursRecalculator())->recalculate($student->fresh());

        $this->assertSame('Completed', $student->fresh()->ojt_status);

        // BR-10: a new draft is blocked with a real validation error.
        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Blocked by completion.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertSessionHasErrors('report_date');

        $this->assertDatabaseCount('daily_accomplishment_reports', 1);
    }
}