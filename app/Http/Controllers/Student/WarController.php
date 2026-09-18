<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitWarRequest;
use App\Http\Requests\UpdateWarWeekRequest;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * BR-8: one WAR document per student per month, filled progressively —
 * Week 1–2 during the month's first Submission Cycle, Week 3–4 during its
 * second. All queries scoped through $request->user()->student (BR-11),
 * mirroring Student\DarController's pattern.
 *
 * AuditLog wiring (this session): submit() logs a 'Submit' row per
 * week-pair submission, matching DarController/MarController's pattern.
 */
class WarController extends Controller
{
    /**
     * Shows (and lazily creates) the current calendar month's WAR. There
     * is no separate "create" step the student has to think about — the
     * row is created empty on first visit, matching "sections fill in
     * progressively" rather than requiring an explicit new-document action.
     */
    public function show(): View
    {
        $student = request()->user()->student;
        $monthStart = now()->startOfMonth()->toDateString();

        // whereDate, not firstOrCreate's exact match: Eloquent's date
        // cast serializes month_period back as 'Y-m-d H:i:s' on save,
        // so a later exact-match lookup for 'Y-m-d' misses on drivers
        // without DATE coercion (sqlite) and firstOrCreate would try a
        // duplicate INSERT -> unique violation. Same portability reason
        // as the whereDate lookups in WarPdfController/CohortAggregator.
        // (MarController::show still uses firstOrCreate — same latent
        // twin, left untouched per this session's MAR-freeze.)
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)
            ->whereDate('month_period', $monthStart)
            ->first();

        if (! $war) {
            $war = WeeklyAccomplishmentReport::create([
                'student_id' => $student->id,
                'month_period' => $monthStart,
            ]);
        }

        // Open cycles under this student's own coordinator (BR-11), for
        // the "submit this week-pair into..." dropdowns.
        $openCycles = $student->coordinator
            ->submissionCycles()
            ->whereDate('deadline_date', '>=', now()->toDateString())
            ->orderBy('deadline_date')
            ->get();

        return view('student.war.show', compact('war', 'openCycles'));
    }

    /**
     * Saves one week-section's content. If that week was previously
     * Returned, saving moves it straight back to Pending for re-review —
     * same resubmission pattern as DarController::update(), and for the
     * same reason: workflows.md §4.3 treats an edit-after-Return as the
     * resubmission itself, not a separate step.
     */
    public function updateWeek(UpdateWarWeekRequest $request, WeeklyAccomplishmentReport $war): RedirectResponse
    {
        $week = $request->integer('week');
        $this->authorize('updateWeek', [$war, $week]);

        $wasReturned = $war->{"week{$week}_status"} === 'Returned';

        $war->{"week{$week}_activities"} = $request->input('activities');
        $war->{"week{$week}_hours"} = $request->input('hours');

        if ($wasReturned) {
            $war->{"week{$week}_status"} = 'Pending';
            $war->{"week{$week}_comment"} = null;
        }

        $war->save();

        return redirect()
            ->route('student.war.show')
            ->with('status', $wasReturned ? "Week {$week} revised and resubmitted." : "Week {$week} saved.");
    }

    /**
     * Submits a week-pair (Week 1–2 or Week 3–4) into a cycle. Which pair
     * is determined by which cycle slot is still open on this document —
     * cycle1_id fills first (Week 1–2), then cycle2_id (Week 3–4), per
     * BR-8's "first cycle of the month, second cycle of the month" order.
     */
    public function submit(SubmitWarRequest $request, WeeklyAccomplishmentReport $war, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('submit', $war);

        $student = $request->user()->student;
        $cycle = $student->coordinator->submissionCycles()->findOrFail($request->integer('cycle_id'));

        if ($war->cycle1_id === null) {
            $weeks = [1, 2];
            $war->cycle1_id = $cycle->id;
        } elseif ($war->cycle2_id === null) {
            $weeks = [3, 4];
            $war->cycle2_id = $cycle->id;
        } else {
            return redirect()
                ->route('student.war.show')
                ->with('status', 'This month\'s WAR already has both week-pairs submitted.');
        }

        // BR-6: Late is decided ONLY by comparing the submission moment
        // against the cycle's deadline, via the same centralized helper
        // DAR submission uses — never by looking at any activity date.
        $status = $cycle->isPastDeadline() ? 'Late' : 'Pending';

        foreach ($weeks as $week) {
            $war->{"week{$week}_status"} = $status;
        }

        $war->save();

        $auditLogger->log(
            $request->user(),
            'Submit',
            "Submitted WAR Week {$weeks[0]}-{$weeks[1]} into cycle '{$cycle->cycle_name}'."
        );

        return redirect()
            ->route('student.war.show')
            ->with('status', "Week {$weeks[0]}–{$weeks[1]} submitted into {$cycle->cycle_name}.");
    }
}
