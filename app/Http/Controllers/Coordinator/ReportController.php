<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\Student;
use App\Models\SubmissionCycle;
use App\Models\WeeklyAccomplishmentReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * blueprint.md §9 — Reports & Exports. THIS SESSION builds the scoped
 * subset the user asked for: Student OJT Progress Report, Submission
 * Monitoring Report, and a plain CSV export. Student Record Report and
 * Department Summary Report are deliberately NOT built here — see
 * PROJECT_STATE.md's Next Steps, carried over on purpose.
 *
 * BR-11/BR-14 throughout: every query in this controller is scoped
 * through $request->user()->coordinator's own relations (->students(),
 * ->submissionCycles()) — never a bare Student::all() or
 * SubmissionCycle::find() — so a coordinator can only ever generate
 * reports covering their own roster, even if handed another
 * coordinator's cycle ID directly. The single-student PDF additionally
 * goes through StudentPolicy::viewReports() as a second layer, matching
 * every other Student-touching controller's pattern in this app
 * (StudentController, DarPdfController, etc.).
 *
 * REUSE NOTE (see PROJECT_STATE.md for the full accounting): the
 * Submission Monitoring Report's per-student, per-document-type
 * counting below reuses the exact same scoping queries
 * DarReviewController::show() / WarReviewController::show() /
 * MarReviewController::show() already use to pull "everything submitted
 * into this cycle for this coordinator's students" — this controller
 * does not re-derive that scoping logic, only tallies statuses on top
 * of it. The cohort-wide dashboard's counting (DashboardController::
 * attachWarMarCounts()/attachOverdueCounts()) is NOT reused as-is,
 * because it deliberately sums PENDING work across ALL of a student's
 * history for an at-a-glance dashboard tile, not per-cycle — this
 * report needs the opposite scope (one specific cycle, all statuses),
 * so it queries fresh rather than repurposing a differently-scoped
 * method.
 */
class ReportController extends Controller
{
    /**
     * Student OJT Progress Report — roster view (blueprint.md §9,
     * row 1). Name, course, company, required/completed/remaining
     * hours, completion %, status — one row per student currently
     * assigned to this coordinator.
     */
    public function progressIndex(): View
    {
        $coordinator = request()->user()->coordinator;

        $students = $coordinator->students()
            ->with(['companyAssignments' => fn ($query) => $query->whereNull('end_date')])
            ->orderBy('surname')
            ->get();

        return view('coordinator.reports.progress-index', compact('coordinator', 'students'));
    }

    /**
     * Same report, all of the coordinator's students, as one PDF.
     * Shares pdf.progress-report with progressPdf() below — that
     * template accepts a collection of any size, so "roster" and
     * "single student" are the same view with a one-vs-many collection,
     * not two templates to keep in sync.
     */
    public function progressIndexPdf(): Response
    {
        $coordinator = request()->user()->coordinator;

        $students = $coordinator->students()
            ->with(['companyAssignments' => fn ($query) => $query->whereNull('end_date')])
            ->orderBy('surname')
            ->get();

        $pdf = Pdf::loadView('pdf.progress-report', [
            'coordinator' => $coordinator,
            'students' => $students,
            'title' => "OJT Progress Report — {$coordinator->full_name}'s Students",
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("OJT-Progress-Report-{$coordinator->full_name}.pdf");
    }

    /**
     * Single-student PDF, linked from the student detail page
     * (coordinator/students/show.blade.php). StudentPolicy::viewReports()
     * gate — see that policy method's docblock for why it delegates to
     * view() rather than duplicating the ownership check.
     */
    public function progressPdf(Student $student): Response
    {
        $this->authorize('viewReports', $student);

        $student->load(['companyAssignments' => fn ($query) => $query->whereNull('end_date')]);
        $coordinator = $student->coordinator;

        $pdf = Pdf::loadView('pdf.progress-report', [
            'coordinator' => $coordinator,
            'students' => collect([$student]),
            'title' => "OJT Progress Report — {$student->fullName()}",
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("OJT-Progress-Report-{$student->student_id_number}.pdf");
    }

    /**
     * Submission Monitoring Report (blueprint.md §9, row 2) — name,
     * cycle, submitted/pending/approved/returned/late counts, scoped to
     * one cycle and this coordinator's own students only.
     *
     * "Submitted" is a derived total (Pending + Approved + Returned +
     * Late), not a sixth independent status — DAR/MAR rows only ever
     * appear in a cycle-scoped query once their cycle_id is set, which
     * only happens at submission (their Draft state has cycle_id null),
     * so every row this method sees is already "submitted" by
     * definition; same logic for WAR weeks via cycle1_id/cycle2_id
     * (Student\WarController::submit() sets both together — see the
     * defensive Draft-skip below for why that invariant is still
     * checked rather than assumed silently).
     */
    public function monitoring(SubmissionCycle $cycle): View
    {
        $coordinator = request()->user()->coordinator;
        abort_unless($cycle->coordinator_id === $coordinator->id, 403);

        $students = $coordinator->students()->orderBy('surname')->get();

        $counts = $students->mapWithKeys(fn (Student $s) => [
            $s->id => ['submitted' => 0, 'pending' => 0, 'approved' => 0, 'returned' => 0, 'late' => 0],
        ]);

        // DAR — same scoping DarReviewController::show() already uses:
        // every row here already belongs to this cycle, and (via the
        // cycle's own ownership check above) to this coordinator's
        // students only.
        foreach ($cycle->dailyAccomplishmentReports as $dar) {
            $this->tally($counts, $dar->student_id, $dar->status);
        }

        // WAR — same scoping WarReviewController::show() already uses.
        $wars = WeeklyAccomplishmentReport::query()
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinator->id))
            ->where(fn ($query) => $query->where('cycle1_id', $cycle->id)->orWhere('cycle2_id', $cycle->id))
            ->get();

        foreach ($wars as $war) {
            foreach ($war->weekPairForCycle($cycle->id) ?? [] as $week) {
                $status = $war->{"week{$week}_status"};

                // Defensive only — submit() always sets both weeks in a
                // pair together (see class docblock), so a Draft week
                // shouldn't reach here in practice. Skipped rather than
                // tallied if it somehow does, so a partially-filled row
                // can never inflate "submitted" past what was actually
                // submitted.
                if ($status !== 'Draft') {
                    $this->tally($counts, $war->student_id, $status);
                }
            }
        }

        // MAR — same scoping MarReviewController::show() already uses.
        $mars = MonthlyAccomplishmentReport::query()
            ->where('cycle_id', $cycle->id)
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinator->id))
            ->get();

        foreach ($mars as $mar) {
            $this->tally($counts, $mar->student_id, $mar->status);
        }

        return view('coordinator.reports.monitoring', compact('cycle', 'students', 'counts'));
    }

    /**
     * @param Collection<int, array{submitted:int,pending:int,approved:int,returned:int,late:int}> $counts
     */
    private function tally(Collection $counts, int $studentId, string $status): void
    {
        if (! $counts->has($studentId)) {
            return;
        }

        $row = $counts->get($studentId);
        $row['submitted']++;

        $key = strtolower($status);
        if (array_key_exists($key, $row)) {
            $row[$key]++;
        }

        $counts->put($studentId, $row);
    }

    /**
     * Excel/CSV Export (blueprint.md §9, row 5) — student info, hours,
     * submission status, for this coordinator's own roster only.
     *
     * Plain CSV via streamDownload, no new package — the user's request
     * this session explicitly said not to add a dependency without real
     * reason, and Laravel's streamDownload + fputcsv is already
     * everything a flat roster export needs.
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $coordinator = request()->user()->coordinator;

        $students = $coordinator->students()
            ->with([
                'user',
                'companyAssignments' => fn ($query) => $query->whereNull('end_date'),
            ])
            ->orderBy('surname')
            ->get();

        $filename = "OJT-Roster-{$coordinator->full_name}.csv";

        return response()->streamDownload(function () use ($students) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Student ID', 'Surname', 'Given Name', 'Middle Name',
                'Course', 'Major', 'Year & Section', 'Company',
                'Required Hours', 'Completed Hours', 'Remaining Hours',
                'Completion %', 'OJT Status', 'Account Status',
            ]);

            foreach ($students as $student) {
                $company = $student->companyAssignments->first();
                $required = (float) $student->required_hours;
                $completed = (float) $student->completed_hours;
                $remaining = max($required - $completed, 0);
                $completionPct = $required > 0 ? round(($completed / $required) * 100, 1) : 0.0;

                fputcsv($handle, [
                    $student->student_id_number,
                    $student->surname,
                    $student->given_name,
                    $student->middle_name,
                    $student->course,
                    $student->major,
                    $student->year_section,
                    $company?->company_name ?? '',
                    $required,
                    $completed,
                    $remaining,
                    $completionPct,
                    $student->ojt_status,
                    $student->user?->status ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
