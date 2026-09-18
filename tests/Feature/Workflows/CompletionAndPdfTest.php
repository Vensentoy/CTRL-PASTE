<?php

namespace Tests\Feature\Workflows;

use App\Models\CompanyAssignment;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\OjtInformationSheet;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\CompletedHoursRecalculator;
use App\Services\DarPdfGrouper;
use App\Services\ReportBundleBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

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
                'activities' => [
                    ['activity' => 'Completion drive work.', 'time_started' => $start, 'time_ended' => $end],
                ],
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
                'activities' => [
                    ['activity' => 'Blocked.', 'time_started' => '08:00', 'time_ended' => '12:00'],
                ],
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
                'activities' => [
                    ['activity' => 'Unblocked after reopen.', 'time_started' => '08:00', 'time_ended' => '12:00'],
                ],
            ])
            ->assertRedirect(route('student.dar.index'));

        $this->assertSame(3, $student->fresh()->dailyAccomplishmentReports()->count());
    }

    public function test_overlapping_war_and_mar_approvals_count_hours_once(): void
    {
        // BR-2/BR-10: MAR's monthly_total_hours is derived from that same
        // month's WAR week1..week4 hours, so approving both the WAR weeks
        // and that month's MAR must count the hours once, not twice.
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.overlap', ['required_hours' => 40]);
        $cycle1 = $this->makeCycle($coordinator, ['cycle_name' => 'Cycle One']);
        $cycle2 = $this->makeCycle($coordinator, ['cycle_name' => 'Cycle Two']);

        WeeklyAccomplishmentReport::create([
            'student_id' => $student->id,
            'month_period' => now()->startOfMonth()->toDateString(),
            'week1_hours' => 8,
            'week2_hours' => 9,
            'week3_hours' => 7,
            'week4_hours' => 10,
        ]);
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        // Submit + approve all four weeks (pair 1-2 into cycle1, 3-4 into cycle2).
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle1->id]);
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle2->id]);

        foreach ([1, 2, 3, 4] as $week) {
            $this->actingAs($coordinator->user)
                ->patch(route('coordinator.war.review.act', $war), [
                    'week' => $week,
                    'decision' => 'approve',
                    'coordinator_comment' => 'Good work.',
                ]);
        }

        $this->assertEquals(34, (float) $student->fresh()->completed_hours);

        // Now submit + approve the SAME month's MAR (derived total = 34).
        $this->actingAs($student->user)->get(route('student.mar.show'));
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->patch(route('student.mar.update', $mar), [
            'activities_text' => 'Monthly rollup of the same four weeks.',
        ]);
        $this->actingAs($student->user)->post(route('student.mar.submit', $mar), ['cycle_id' => $cycle1->id]);
        $this->assertEquals(34, (float) $mar->fresh()->monthly_total_hours);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.mar.review.act', $mar), [
                'decision' => 'approve',
                'coordinator_comment' => 'Approved.',
            ]);

        // Hours counted once (34), not twice (68) — and still Ongoing
        // against the 40h requirement, not prematurely Completed.
        $this->assertEquals(34, (float) $student->fresh()->completed_hours);
        $this->assertSame('Ongoing', $student->fresh()->ojt_status);
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

    public function test_info_sheet_pdf_streams_and_scopes_by_ownership(): void
    {
        // pdf-forms.md §1 — the one-time OJT Information Sheet prints like
        // DAR/WAR/MAR: owning student + owning coordinator may print it,
        // anyone else is denied (BR-11/BR-14).
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.infosheet');

        OjtInformationSheet::create([
            'student_id' => $student->id,
            'city_address' => '123 Legaspi St',
            'gender' => 'Female',
            'contact_number' => '09171234567',
            'email' => 'jane.doe@example.com',
            'birth_date' => '2005-01-15',
            'birth_place' => 'Naga City',
            'signed_date' => now()->toDateString(),
        ]);

        $url = route('info-sheet.pdf', $student->id);

        // Owning student can print their own sheet.
        $response = $this->actingAs($student->user)->get($url);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        // Owning coordinator can print it for filing.
        $this->actingAs($coordA->user)->get($url)->assertOk();

        // Non-owning coordinator is denied.
        $this->actingAs($coordB->user)->get($url)->assertForbidden();
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
            'activities' => ['Pdf week line one.', 'Pdf week line two.'],
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

    public function test_dar_pdf_at_item_cap_batches_cleanly(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.pdfcap');
        $cycle = $this->makeCycle($coordinator);

        // Lester-shaped date: 4 entries, 9h total.
        $lesterDar = $this->makeDar($student, [
            'report_date' => now()->subDay()->toDateString(),
            'activities' => $this->lesterDayActivities(),
            'remarks_student' => 'COMPLETED',
        ]);
        $this->assertEquals(9.0, (float) $lesterDar->hours_rendered);

        // Cap-scale date: exactly 20 entries x 30min = 10h.
        $capEntries = [];
        for ($i = 1; $i <= 20; $i++) {
            $capEntries[] = ['activity' => "Cap task {$i}.", 'time_started' => '08:00', 'time_ended' => '08:30'];
        }
        $capDar = $this->makeDar($student, [
            'report_date' => now()->toDateString(),
            'activities' => $capEntries,
            'remarks_student' => 'COMPLETED',
        ]);
        $this->assertEquals(10.0, (float) $capDar->hours_rendered);

        // Four more single-entry dates (4h each) to push past the
        // 5-dates-per-printout batch boundary: 6 dates -> 2 batches.
        $extraIds = [];
        for ($d = 2; $d <= 5; $d++) {
            $extraIds[] = $this->makeDar($student, [
                'report_date' => now()->subDays($d)->toDateString(),
            ])->id;
        }

        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => array_merge([$lesterDar->id, $capDar->id], $extraIds),
        ]);

        // HTTP route streams a real PDF across the two printout pages.
        $response = $this->actingAs($student->user)
            ->get(route('dar.pdf', ['cycle' => $cycle->id, 'student' => $student->id]));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        $dars = $student->dailyAccomplishmentReports()
            ->where('cycle_id', $cycle->id)
            ->orderBy('report_date')
            ->get();

        // Batching: dates are never split — 6 dates become 5+1, and each
        // batch total sums the STORED multi-entry hours.
        $grouper = new DarPdfGrouper();
        $batches = $grouper->batch($dars);

        $this->assertCount(2, $batches);
        $this->assertCount(5, $batches->first());
        $this->assertCount(1, $batches->last());
        // Ascending by date: the four 4h extras + the 9h Lester date fill
        // batch one, the 10h cap date stands alone in batch two.
        $this->assertEquals(4 * 4.0 + 9.0, $grouper->batchTotalHours($batches->first()));
        $this->assertEquals(10.0, $grouper->batchTotalHours($batches->last()));

        // Blade structure on the exact view data the PDF uses: merged
        // cells span each date's entries; per-date subtotals match.
        $viewData = [
            'student' => $student,
            'company' => null,
            'cycle' => $cycle,
            'batches' => $batches,
            'grouper' => $grouper,
        ];
        $html = view('pdf.dar', $viewData)->render();

        $this->assertStringContainsString('rowspan="4"', $html);
        $this->assertStringContainsString('rowspan="20"', $html);
        $this->assertStringContainsString('Cap task 20.', $html);
        $this->assertStringContainsString('COMPLETED', $html);
        $this->assertStringContainsString('9.00', $html);
        $this->assertStringContainsString('10.00', $html);

        // DomPDF renders the cap-scale table without exception and emits
        // a real PDF document. Whether a 20-row table flows gracefully
        // across the page stays a manual visual check against the Lester
        // reference — bytes can't assert "not awkward".
        $pdfBytes = Pdf::loadView('pdf.dar', $viewData)
            ->setPaper([0, 0, 612, 936], 'portrait')
            ->output();

        $this->assertNotEmpty($pdfBytes);
        $this->assertSame('%PDF', substr($pdfBytes, 0, 4));
    }

    private function makeBundlableStudent($coordinator, string $username): array
    {
        $student = $this->makeStudent($coordinator, $username);
        $cycle = $this->makeCycle($coordinator);
        $month = now()->startOfMonth()->toDateString();

        // Submitted multi-entry DAR in the cycle.
        $this->makeDar($student, [
            'report_date' => now()->toDateString(),
            'cycle_id' => $cycle->id,
            'status' => 'Pending',
            'activities' => [
                ['activity' => 'Bundle task one.', 'time_started' => '08:00', 'time_ended' => '10:00'],
                ['activity' => 'Bundle task two.', 'time_started' => '10:00', 'time_ended' => '12:00'],
            ],
        ]);

        // Submitted WAR week in the same month.
        WeeklyAccomplishmentReport::create([
            'student_id' => $student->id, 'month_period' => $month,
            'week1_activities' => ['Bundle week line one.', 'Bundle week line two.'],
            'week1_hours' => 8, 'week1_status' => 'Pending', 'week1_comment' => null,
            'cycle1_id' => $cycle->id,
        ]);

        // Submitted MAR for the same month.
        MonthlyAccomplishmentReport::create([
            'student_id' => $student->id, 'month_period' => $month,
            'activities_text' => 'Bundle month summary.',
            'monthly_total_hours' => 8, 'status' => 'Pending', 'cycle_id' => $cycle->id,
        ]);

        return [$student, $cycle];
    }

    public function test_report_bundle_downloads_zip_for_own_student(): void
    {
        $coordinator = $this->makeCoordinator();
        [$student, $cycle] = $this->makeBundlableStudent($coordinator, 'student.bundle');

        $response = $this->actingAs($student->user)
            ->get(route('reports.bundle', $student));

        $response->assertOk();
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            "Reports-{$student->student_id_number}.zip",
            (string) $response->headers->get('Content-Disposition')
        );

        // Owning coordinator gets the same bundle.
        $this->actingAs($coordinator->user)
            ->get(route('reports.bundle', $student))
            ->assertOk();
    }

    public function test_report_bundle_contains_one_member_per_submitted_document(): void
    {
        $coordinator = $this->makeCoordinator();
        [$student] = $this->makeBundlableStudent($coordinator, 'student.bundlezip');

        // Builder-level: open the real ZIP and pin its members. Done
        // directly (not via the download response) so the test never
        // depends on BinaryFileResponse deletion timing.
        $builder = new ReportBundleBuilder(new DarPdfGrouper());
        $zipPath = $builder->build($student);

        try {
            $zip = new ZipArchive();
            $this->assertSame(true, $zip->open($zipPath));

            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $names[] = $stat['name'];
                // Every member is a real, non-empty PDF.
                $this->assertGreaterThan(0, $stat['size']);
                $this->assertStringEndsWith('.pdf', $stat['name']);
            }
            $zip->close();

            $this->assertCount(3, $names);
            $this->assertCount(1, preg_grep('/^DAR-/', $names));
            $this->assertCount(1, preg_grep('/^WAR-/', $names));
            $this->assertCount(1, preg_grep('/^MAR-/', $names));
        } finally {
            @unlink($zipPath);
        }

        // Incremental-build hygiene: per-document temp files are removed
        // at close() — only finished bundles may remain in the tmp dir.
        $leftovers = glob(storage_path('app/tmp/bundles/doc*')) ?: [];
        $this->assertSame([], $leftovers);
    }

    public function test_report_bundle_is_denied_for_non_owners(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        [$student] = $this->makeBundlableStudent($coordA, 'student.bundleown');
        $intruder = $this->makeStudent($coordA, 'student.intruder');

        // Non-owning coordinator is denied (BR-11/BR-14).
        $this->actingAs($coordB->user)
            ->get(route('reports.bundle', $student))
            ->assertForbidden();

        // Another student is denied, even under the same coordinator.
        $this->actingAs($intruder->user)
            ->get(route('reports.bundle', $student))
            ->assertForbidden();
    }

    public function test_report_bundle_with_nothing_submitted_is_404(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.emptybundle');

        // Draft-only DAR exists but nothing submitted — mirrors the
        // single-download controllers' empty-case 404.
        $this->makeDar($student, ['report_date' => now()->toDateString()]);

        $this->actingAs($student->user)
            ->get(route('reports.bundle', $student))
            ->assertNotFound();
    }
}