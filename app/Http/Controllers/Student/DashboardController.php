<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\Student;
use App\Models\SubmissionCycle;
use App\Models\WeeklyAccomplishmentReport;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Student landing page after login. Read-only summary view — no writes
 * happen here, so no Form Request / policy checks beyond the route-level
 * 'role:student' middleware (BR-14 layer 1) are needed; every query below
 * is already scoped to $request->user()->student, so a student can only
 * ever see their own data (BR-11's isolation principle applied to self).
 *
 * This session added: per-document status (DAR/WAR/MAR) for the active
 * cycle (BR-7), a display-only Late/Missed label derived purely from
 * SubmissionCycle::isPastDeadline() (BR-6), and the data needed for a
 * proper Completed/locked banner (BR-10). Everything that already
 * existed here — hours progress, active company, DAR history/counts —
 * is unchanged in behavior, only reorganized slightly to share the
 * $activeCycle lookup (previously named $upcomingCycle; renamed for
 * clarity now that it's used for more than the "next deadline" card).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $student = request()->user()->student;

        $activeCompany = $student->activeCompanyAssignment();

        // Nearest not-yet-passed cycle deadline for this student's own
        // coordinator (BR-11) — gives the student a "next thing due" cue,
        // and is also the cycle used below for per-document status. This
        // is the same "which cycle is open right now" logic
        // Dar/War/MarController already use when populating their
        // "submit into..." dropdowns — not duplicated differently here.
        $activeCycle = $student->coordinator
            ->submissionCycles()
            ->whereDate('deadline_date', '>=', now()->toDateString())
            ->orderBy('deadline_date')
            ->first();

        $darCounts = $student->dailyAccomplishmentReports()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentDars = $student->dailyAccomplishmentReports()
            ->orderByDesc('report_date')
            ->limit(5)
            ->get();

        // completed_hours/required_hours are read directly off the
        // Student row — NOT recomputed here. CompletedHoursRecalculator
        // is deliberately only invoked from review actions (see its own
        // docblock and DarReviewController::review()); a page view is a
        // read, not a trigger, so this reads the already-derived cached
        // value rather than re-running the aggregate queries on every
        // dashboard load.
        $hoursRemaining = max(0, $student->required_hours - $student->completed_hours);
        $hoursPercent = $student->required_hours > 0
            ? min(100, round(($student->completed_hours / $student->required_hours) * 100))
            : 0;

        // Per-document status for the active cycle only (BR-7: each
        // document type's status is independent of the others). Null
        // when there's no open cycle at all, so the view can show a
        // simple "no open cycle" state instead of three empty badges.
        $cycleDocStatus = $activeCycle
            ? $this->activeCycleDocumentStatus($student, $activeCycle)
            : null;

        return view('student.dashboard', compact(
            'student',
            'activeCompany',
            'activeCycle',
            'darCounts',
            'recentDars',
            'hoursRemaining',
            'hoursPercent',
            'cycleDocStatus',
        ));
    }

    /**
     * Computes a single display status per document type (DAR/WAR/MAR)
     * for ONE specific cycle. Every real status value used here (Draft,
     * Pending, Late, Returned, Approved) already exists in the schema
     * and is set exclusively by the student-submit or coordinator-review
     * controllers elsewhere in the app — this method never writes
     * anything, it only reads and rolls values up for display.
     *
     * "Missed" is the one label that is NEVER stored anywhere (it does
     * not appear in data-model.md's status list for any of the three
     * document types). It is derived here, and only here, using
     * precisely BR-6's own rule — SubmissionCycle::isPastDeadline() —
     * with nothing submitted into that cycle. If the deadline hasn't
     * passed yet and nothing is submitted, the label is "Draft" (still
     * time to act), never "Missed".
     */
    private function activeCycleDocumentStatus(Student $student, SubmissionCycle $cycle): array
    {
        $pastDeadline = $cycle->isPastDeadline();
        $notSubmittedLabel = $pastDeadline ? 'Missed' : 'Draft';

        // DAR: one cycle can hold many rows (one per logged day). Rolled
        // up with the same priority order
        // WeeklyAccomplishmentReport::getOverallStatusAttribute() already
        // uses for its four week-sections, so a "block status" reads
        // consistently everywhere in the app rather than inventing a
        // second rollup rule.
        $darStatuses = $student->dailyAccomplishmentReports()
            ->where('cycle_id', $cycle->id)
            ->pluck('status');

        $darStatus = $darStatuses->isEmpty()
            ? $notSubmittedLabel
            : $this->rollupStatus($darStatuses);

        // WAR: one row per month; this cycle corresponds to whichever
        // week-pair slot (1–2 or 3–4) it was submitted into, per BR-8.
        // weekPairForCycle() is the model's own existing helper — not
        // reimplemented here.
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)
            ->whereDate('month_period', now()->startOfMonth()->toDateString())
            ->first();

        $warPair = $war?->weekPairForCycle($cycle->id);

        $warStatus = $warPair === null
            ? $notSubmittedLabel
            : $this->rollupStatus(collect($warPair)->map(fn ($week) => $war->{"week{$week}_status"}));

        // MAR: single status field, single cycle_id — no rollup needed,
        // just a direct membership check against this cycle.
        $mar = MonthlyAccomplishmentReport::where('student_id', $student->id)
            ->whereDate('month_period', now()->startOfMonth()->toDateString())
            ->first();

        $marStatus = (! $mar || $mar->cycle_id !== $cycle->id)
            ? $notSubmittedLabel
            : $mar->status;

        return [
            'dar' => $darStatus,
            'war' => $warStatus,
            'mar' => $marStatus,
        ];
    }

    /**
     * Same rollup priority WeeklyAccomplishmentReport
     * ::getOverallStatusAttribute() uses across its four week-sections
     * (Returned > Late > all-Approved > Pending > Draft) — reused here
     * for a DAR cycle's rows and a WAR week-pair's two statuses, rather
     * than duplicating slightly-different branch logic in three places.
     */
    private function rollupStatus(Collection $statuses): string
    {
        if ($statuses->contains('Returned')) {
            return 'Returned';
        }

        if ($statuses->contains('Late')) {
            return 'Late';
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === 'Approved')) {
            return 'Approved';
        }

        if ($statuses->contains('Pending')) {
            return 'Pending';
        }

        return 'Draft';
    }
}
