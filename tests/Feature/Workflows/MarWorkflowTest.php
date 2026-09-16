<?php

namespace Tests\Feature\Workflows;

use App\Models\MonthlyAccomplishmentReport;
use App\Models\WeeklyAccomplishmentReport;

/**
 * workflows.md §3 — Monthly Accomplishment Report. Single status per row
 * (like DAR, not WAR's four week-sections). monthly_total_hours is
 * derived from that month's WAR row (Summary of Weekly Accomplishment
 * Reports per pdf-forms.md, resolved in this session) — never accepted
 * as direct input.
 */
class MarWorkflowTest extends WorkflowTestCase
{
    private function makeWarRow(int $studentId): WeeklyAccomplishmentReport
    {
        return WeeklyAccomplishmentReport::create([
            'student_id' => $studentId,
            'month_period' => now()->startOfMonth()->toDateString(),
            'week1_hours' => 8,
            'week2_hours' => 9,
            'week3_hours' => 7,
            'week4_hours' => 10,
        ]);
    }

    public function test_show_lazily_creates_the_current_months_mar(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar');

        $this->actingAs($student->user)
            ->get(route('student.mar.show'))
            ->assertOk();

        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->assertNotNull($mar);
        $this->assertEquals(
            now()->startOfMonth()->toDateString(),
            $mar->month_period->format('Y-m-d')
        );
        $this->assertSame('Draft', $mar->status);
    }

    public function test_saving_the_mar_derives_total_hours_from_the_months_war(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar');
        $this->makeWarRow($student->id); // 8+9+7+10 = 34

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->patch(route('student.mar.update', $mar), [
                'activities_text' => 'Monthly summary of work.',
                'remarks' => 'All good this month.',
            ])
            ->assertRedirect(route('student.mar.show'));

        $fresh = $mar->fresh();
        $this->assertSame('Monthly summary of work.', $fresh->activities_text);
        $this->assertEquals(34, (float) $fresh->monthly_total_hours);
        $this->assertSame('Draft', $fresh->status);
    }

    public function test_submit_moves_the_mar_into_a_cycle(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar');
        $this->makeWarRow($student->id);
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle->id])
            ->assertRedirect(route('student.mar.show'));

        $fresh = $mar->fresh();
        $this->assertSame($cycle->id, $fresh->cycle_id);
        $this->assertSame('Pending', $fresh->status);
        $this->assertEquals(34, (float) $fresh->monthly_total_hours);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Submit']);
    }

    public function test_coordinator_can_approve_the_mar_and_hours_are_added(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar', ['required_hours' => 40]);
        $this->makeWarRow($student->id); // 34h
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle->id]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.mar.review.act', $mar), [
                'decision' => 'approve',
                'coordinator_comment' => 'Approved.',
            ])
            ->assertRedirect(route('coordinator.mar.review', $cycle));

        $this->assertSame('Approved', $mar->fresh()->status);
        $this->assertEquals(34, (float) $student->fresh()->completed_hours);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Approve']);
    }

    public function test_return_requires_a_comment(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar');
        $this->makeWarRow($student->id);
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle->id]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.mar.review.act', $mar), [
                'decision' => 'return',
                'coordinator_comment' => '',
            ])
            ->assertSessionHasErrors('coordinator_comment');

        $this->assertSame('Pending', $mar->fresh()->status);
    }

    public function test_return_with_comment_sends_it_back_to_pending_via_resubmit(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar');
        $this->makeWarRow($student->id);
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle->id]);

        $this->actingAs($coordinator->user)->patch(route('coordinator.mar.review.act', $mar), [
            'decision' => 'return',
            'coordinator_comment' => 'Needs more detail.',
        ]);

        $this->assertSame('Returned', $mar->fresh()->status);
        $this->assertSame('Needs more detail.', $mar->fresh()->coordinator_comment);

        // Workflows.md §4.3: an edit after Return = resubmission, back to
        // Pending with the comment cleared.
        $this->actingAs($student->user)
            ->patch(route('student.mar.update', $mar), [
                'activities_text' => 'Revised monthly summary.',
                'remarks' => 'Fixed.',
            ])
            ->assertRedirect(route('student.mar.show'));

        $fresh = $mar->fresh();
        $this->assertSame('Pending', $fresh->status);
        $this->assertNull($fresh->coordinator_comment);
    }

    public function test_completed_student_cannot_save_mar_content(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.mar', ['ojt_status' => 'Completed']);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->patch(route('student.mar.update', $mar), [
                'activities_text' => 'Blocked content.',
            ])
            ->assertSessionHasErrors('activities_text');
    }
}