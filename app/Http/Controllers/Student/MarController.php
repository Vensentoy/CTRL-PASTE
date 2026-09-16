<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitMarRequest;
use App\Http\Requests\UpdateMarRequest;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * MAR (data-model.md): one row per student per month, submitted once
 * the month's coverage is complete — structurally closer to DAR (single
 * status field, cycle_id, coordinator_comment, reviewed_by/at) than to
 * WAR's four independent week-sections.
 *
 * This controller is a deliberate hybrid: the lazy-create-on-visit
 * pattern in show() is borrowed from WarController::show() (WAR is also
 * one row per month), but update()/submit()'s single-status shape
 * mirrors DarController's, not WAR's per-week updateWeek()/submit().
 * See PROJECT_STATE.md for why this isn't a literal "mirror WAR" clone.
 *
 * monthly_total_hours auto-compute (this session): resolves the
 * judgment call flagged in PROJECT_STATE.md's open items —
 * pdf-forms.md describes the printed MAR as a "Summary of Weekly
 * Accomplishment Reports," so this value is now derived from that
 * student's WAR row for the same month_period rather than typed in
 * fresh, via monthlyHoursFromWar() below. This mirrors how BR-3 treats
 * DAR's hours_rendered — never accepted as direct input, always
 * computed server-side. If no WAR row exists yet for the month, this
 * resolves to 0 rather than erroring, so a student can still save/
 * submit a MAR before touching that month's WAR.
 *
 * All queries scoped through $request->user()->student (BR-11), same
 * as every other student-facing controller in the app.
 */
class MarController extends Controller
{
    /**
     * Shows (and lazily creates) the current calendar month's MAR —
     * same "no separate create step" pattern as WarController::show().
     */
    public function show(): View
    {
        $student = request()->user()->student;
        $monthStart = now()->startOfMonth()->toDateString();

        $mar = MonthlyAccomplishmentReport::firstOrCreate(
            ['student_id' => $student->id, 'month_period' => $monthStart],
            ['status' => 'Draft'],
        );

        // Open cycles under this student's own coordinator (BR-11), for
        // the "submit into..." dropdown.
        $openCycles = $student->coordinator
            ->submissionCycles()
            ->whereDate('deadline_date', '>=', now()->toDateString())
            ->orderBy('deadline_date')
            ->get();

        $computedHours = $this->monthlyHoursFromWar($student->id, $mar->month_period->toDateString());

        return view('student.mar.show', compact('mar', 'openCycles', 'computedHours'));
    }

    /**
     * Saves MAR content. If previously Returned, saving moves it
     * straight back to Pending for re-review — same resubmission
     * pattern as DarController::update()/WarController::updateWeek().
     *
     * monthly_total_hours is no longer read from the request (see
     * UpdateMarRequest) — it's recomputed here from that month's WAR on
     * every save, so it always reflects the current WAR content even if
     * the student edits WAR hours after already saving the MAR once.
     */
    public function update(UpdateMarRequest $request, MonthlyAccomplishmentReport $mar): RedirectResponse
    {
        $this->authorize('update', $mar);

        $wasReturned = $mar->status === 'Returned';

        $mar->activities_text = $request->input('activities_text');
        $mar->monthly_total_hours = $this->monthlyHoursFromWar($mar->student_id, $mar->month_period->toDateString());
        $mar->remarks = $request->input('remarks');

        if ($wasReturned) {
            $mar->status = 'Pending';
            $mar->coordinator_comment = null;
        }

        $mar->save();

        return redirect()
            ->route('student.mar.show')
            ->with('status', $wasReturned ? 'Revised MAR resubmitted for review.' : 'Draft saved.');
    }

    /**
     * Submits the current MAR into a cycle. Unlike WAR's two cycle
     * slots, a MAR row has exactly one cycle_id — submitted once.
     * Judgment call, flagged in PROJECT_STATE.md: not hard-gated on the
     * month's WAR (Week 1–4) actually being complete first, since
     * data-model.md establishes no such dependency between the two
     * entities — the UI just notes the usual order.
     *
     * monthly_total_hours is recomputed one final time here, in case the
     * student edited that month's WAR after last saving the MAR draft
     * but before submitting.
     */
    public function submit(SubmitMarRequest $request, MonthlyAccomplishmentReport $mar, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('submit', $mar);

        $student = $request->user()->student;
        $cycle = $student->coordinator->submissionCycles()->findOrFail($request->integer('cycle_id'));

        $mar->monthly_total_hours = $this->monthlyHoursFromWar($mar->student_id, $mar->month_period->toDateString());
        $mar->cycle_id = $cycle->id;
        // BR-6: lateness decided ONLY against the cycle's own deadline,
        // via the same centralized helper DAR/WAR use — never against
        // month_period.
        $mar->status = $cycle->isPastDeadline() ? 'Late' : 'Pending';
        $mar->save();

        $auditLogger->log(
            $request->user(),
            'Submit',
            "Submitted MAR for {$mar->month_period->format('F Y')} into cycle '{$cycle->cycle_name}'."
        );

        return redirect()
            ->route('student.mar.show')
            ->with('status', "MAR for {$mar->month_period->format('F Y')} submitted into {$cycle->cycle_name}.");
    }

    /**
     * Sum of week1_hours..week4_hours from the WAR row matching this
     * student_id + month_period, or 0 if no such row exists yet.
     */
    private function monthlyHoursFromWar(int $studentId, string $monthPeriod): float
    {
        $war = WeeklyAccomplishmentReport::where('student_id', $studentId)
            ->whereDate('month_period', $monthPeriod)
            ->first();

        if (! $war) {
            return 0.0;
        }

        return (float) ($war->week1_hours + $war->week2_hours + $war->week3_hours + $war->week4_hours);
    }
}
