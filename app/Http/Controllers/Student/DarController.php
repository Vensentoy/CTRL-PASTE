<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDarRequest;
use App\Http\Requests\SubmitDarRequest;
use App\Http\Requests\UpdateDarRequest;
use App\Models\DailyAccomplishmentReport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Workflows.md §2 (Daily Logging) and §3 step 3 (batch submit).
 * All queries below are scoped through $request->user()->student — never
 * a bare DailyAccomplishmentReport::all()/find() — so a student cannot
 * reach another student's rows even with a guessed ID (BR-11/BR-14).
 * Route-model-bound {dar} params are still re-checked via DarPolicy on
 * every action as a second layer, per StudentPolicy's existing pattern.
 *
 * AuditLog wiring (this session): submit() logs one 'Submit' row per
 * batch action (not per individual report) — the audit trail records
 * "student submitted N reports into cycle X," matching how the status
 * message already summarizes the action, rather than N near-duplicate
 * rows for one click.
 */
class DarController extends Controller
{
    /**
     * Drafts (cycle_id null) plus everything already submitted, so the
     * student has one place to see both "still editable" and "in
     * review" reports. Split in the view, not the query.
     */
    public function index(): View
    {
        $student = request()->user()->student;

        $dars = $student->dailyAccomplishmentReports()
            ->orderByDesc('report_date')
            ->get();

        // Cycles belonging to this student's OWN coordinator (BR-11) —
        // used to populate the "submit into" dropdown for drafts.
        $cycles = $student->coordinator
            ->submissionCycles()
            ->orderByDesc('deadline_date')
            ->get();

        return view('student.dar.index', compact('dars', 'cycles'));
    }

    public function create(): View
    {
        return view('student.dar.form', [
            'dar' => new DailyAccomplishmentReport(),
        ]);
    }

    public function store(StoreDarRequest $request): RedirectResponse
    {
        $student = $request->user()->student;
        $validated = $request->validated();

        $dar = new DailyAccomplishmentReport($validated);
        $dar->student_id = $student->id;
        $dar->status = 'Draft';
        // BR-3: hours_rendered is ALWAYS computed server-side, never
        // taken from the request — calculate after the raw times are
        // set, before the first save.
        $dar->hours_rendered = $dar->calculateHoursRendered();
        $dar->save();

        return redirect()
            ->route('student.dar.index')
            ->with('status', 'Draft saved for '.$dar->report_date->toFormattedDateString().'.');
    }

    public function edit(DailyAccomplishmentReport $dar): View
    {
        $this->authorize('update', $dar);

        return view('student.dar.form', compact('dar'));
    }

    public function update(UpdateDarRequest $request, DailyAccomplishmentReport $dar): RedirectResponse
    {
        $this->authorize('update', $dar);

        $wasReturned = $dar->status === 'Returned';

        $dar->fill($request->validated());
        // BR-3: recompute on every write that touches the time fields —
        // never trust a client-sent hours_rendered (there isn't one;
        // it's excluded from $fillable entirely).
        $dar->hours_rendered = $dar->calculateHoursRendered();

        // workflows.md §4.3: resubmitting a Returned document goes
        // straight back to Pending for re-review — this is NOT
        // re-checked against the cycle deadline (BR-6's Late flag is
        // decided once, at the original submission moment, not on
        // every subsequent edit).
        if ($wasReturned) {
            $dar->status = 'Pending';
            $dar->coordinator_comment = null;
        }

        $dar->save();

        return redirect()
            ->route('student.dar.index')
            ->with('status', $wasReturned ? 'Revised report resubmitted for review.' : 'Draft updated.');
    }

    public function destroy(DailyAccomplishmentReport $dar): RedirectResponse
    {
        $this->authorize('delete', $dar);

        // BR-9: SoftDeletes on the model makes this recoverable, never
        // a hard delete — no ->forceDelete() path exists in this
        // controller.
        $dar->delete();

        return redirect()
            ->route('student.dar.index')
            ->with('status', 'Draft deleted.');
    }

    /**
     * Batch-submit selected drafts into a SubmissionCycle
     * (workflows.md §3 step 3). Each row transitions independently
     * (BR-7) to Pending or Late depending on
     * SubmissionCycle::isPastDeadline() (BR-6) — never compared against
     * the report's own report_date.
     */
    public function submit(SubmitDarRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $student = $request->user()->student;
        $cycle = $student->coordinator->submissionCycles()->findOrFail($request->integer('cycle_id'));

        $dars = $student->dailyAccomplishmentReports()
            ->whereIn('id', $request->input('dar_ids'))
            ->whereNull('cycle_id')
            ->get();

        $submittedCount = 0;

        foreach ($dars as $dar) {
            $this->authorize('submit', $dar);

            $dar->cycle_id = $cycle->id;
            // BR-6: lateness is decided ONLY by comparing the submission
            // moment (now) against the cycle's deadline_date, via the
            // one centralized helper — never by looking at report_date.
            $dar->status = $cycle->isPastDeadline() ? 'Late' : 'Pending';
            $dar->save();
            $submittedCount++;
        }

        if ($submittedCount > 0) {
            $auditLogger->log(
                $request->user(),
                'Submit',
                "Submitted {$submittedCount} DAR(s) into cycle '{$cycle->cycle_name}'."
            );
        }

        return redirect()
            ->route('student.dar.index')
            ->with('status', "Submitted {$submittedCount} report(s) into {$cycle->cycle_name}.");
    }
}
