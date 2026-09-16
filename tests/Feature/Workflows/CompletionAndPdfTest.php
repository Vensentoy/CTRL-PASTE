<?php

namespace Tests\Feature\Workflows;

use App\Models\CompanyAssignment;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\CompletedHoursRecalculator;

/**
 * workflows.md §5 (completion, finalize/archive) + §6 (official-format
 * PDFs). BR-10 closure lock is exercised end-to-end here, and each PDF
 * route is smoke-tested for an actual application/pdf stream plus the
 * BR-11/BR-14 ownership denial on a guessed cross-coordinator hit.
 */
class CompletionAndPdfTest extends WorkflowTestCase
{
    private function driveStudentToCompletion($coordinator, $student): array
    {
        $cycle = $this->makeCycle($coordinator);

        // Two approved DARs = 4.5 + 4.5 = 9h against required 8h.
        foreach ([['08:00', '12:30'], ['08:00', '12:30']] as [$start, $end]) {
            $dar = $this->makeDar($student, [
                'report_date' => now()->toDateString(),
                'time_started' => $start,
                'time_ended' => $end,
            ]);
            $this->actingAs($student->user)->post(route('student.dar.submit'), [
                'cycle_id' => $cycle->id,
                'dar_ids' => [$dar->id],
            ]);
            $this->actingAs($coordinator->user)->patch(route('coordinator.dar.review.act', $dar), [
                'decision' => 'approve',
                'coordinator_comment' => 'OK.',
            ]);
        }

        return [$cycle];
    }

    public function test_full_completion_flow_then_reopen_unblocks(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.complete', ['required_hours' => 8]);

        $this->driveStudentToCompletion($coordinator, $student);

        $student = $student->fresh();
        $this->assertGreaterThanOrEqual(8, $student->completed_hours);
        $this->assertSame('Completed', $student->ojt_status);

        // BR-10: locked out of new drafts while Completed.
        $this->actingAs($student->user)
            ->post(route('student.dar.store'), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Blocked.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertSessionHasErrors('report_date');

        // Coordinator reopens (BR-10), the lock drops.
        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.students.reopen', $student))
            ->assertRedirect(route('coordinator.students.show', $student));

        $this->assertSame('Ongoing', $student->fresh()->ojt_status);

        // Use a freshly-fetched user: actingAs() reuses the same model
        // instance, and its cached `student` relation still holds the
        // pre-reopen Completed row.
        $this->actingAs(\App\Models\User::find($student->user_id))
            ->post(route('student.dar.store'), [
                'report_date' => now()->toDateString(),
                'activities_text' => 'Unblocked after reopen.',
                'time_started' => '08:00',
                'time_ended' => '12:00',
            ])
            ->assertRedirect(route('student.dar.index'));

        $this->assertSame(3, $student->fresh()->dailyAccomplishmentReports()->count());
    }

    public function test_archive_is_terminal_and_only_for_completed_records(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.complete', ['required_hours' => 8]);

        // Not completed yet -> archive refuses, record unchanged.
        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.students.archive', $student))
            ->assertRedirect(route('coordinator.students.show', $student));

        $this->assertSame('Active', $student->fresh()->user->status);

        // Drive to Completed, then archive.
        $this->driveStudentToCompletion($coordinator, $student);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.students.archive', $student))
            ->assertRedirect(route('coordinator.students.show', $student));

        $this->assertSame('Archived', $student->fresh()->user->status);

        // Terminal: reopen is blocked too.
        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.students.reopen', $student))
            ->assertRedirect(route('coordinator.students.show', $student));

        $this->assertSame('Completed', $student->fresh()->ojt_status);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'AccountChange']);
    }

    public function test_coordinator_password_reset_forces_change_on_next_login(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.password');

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.students.reset-password', $student))
            ->assertRedirect(route('coordinator.students.show', $student))
            ->assertSessionHas('newTempPassword');

        $this->assertTrue($student->fresh()->user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'AccountChange']);
    }

    public function test_dar_pdf_streams_and_scopes_by_ownership(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.pdf');
        $cycle = $this->makeCycle($coordinator);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $url = route('dar.pdf', ['cycle' => $cycle->id, 'student' => $student->id]);

        // Owning student can print their own batch.
        $response = $this->actingAs($student->user)->get($url);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        // Owning coordinator can print it for filing.
        $response = $this->actingAs($coordinator->user)->get($url);
        $response->assertOk();
    }

    public function test_pdf_is_denied_for_a_non_owning_coordinator(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.pdf');
        $cycle = $this->makeCycle($coordA);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordB->user)
            ->get(route('dar.pdf', ['cycle' => $cycle->id, 'student' => $student->id]))
            ->assertForbidden();
    }

    public function test_war_and_mar_pdfs_stream(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.pdf');
        $cycle = $this->makeCycle($coordinator);
        $month = now()->format('Y-m');

        CompanyAssignment::create([
            'student_id' => $student->id,
            'company_name' => 'ACME Corp',
            'start_date' => now()->subWeek()->toDateString(),
        ]);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->patch(route('student.war.week.update', $war), [
            'week' => 1,
            'activities' => 'Pdf week.',
            'hours' => 8,
        ]);
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);

        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->patch(route('student.mar.update', $mar), [
            'activities_text' => 'Pdf month summary.',
        ]);
        $this->actingAs($student->user)->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle->id]);

        $warPdf = $this->actingAs($student->user)
            ->get(route('war.pdf', ['student' => $student->id, 'month' => $month]));
        $warPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $warPdf->headers->get('Content-Type'));

        $marPdf = $this->actingAs($student->user)
            ->get(route('mar.pdf', ['student' => $student->id, 'month' => $month]));
        $marPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $marPdf->headers->get('Content-Type'));
    }
}