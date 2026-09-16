<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewDarRequest;
use App\Models\DailyAccomplishmentReport;
use App\Models\SubmissionCycle;
use App\Services\AuditLogger;
use App\Services\CompletedHoursRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Workflows.md §4 — Coordinator Review. Opens ONE cycle and reviews
 * submissions per document, per student — never as one all-or-nothing
 * batch (explicit in workflows.md step 1), which is why review() below
 * only ever transitions a single DAR row per request.
 *
 * AuditLog wiring (this session): review() logs an 'Approve' or
 * 'Return' row (data-model.md's action_type values map directly onto
 * the two possible decisions here).
 */
class DarReviewController extends Controller
{
    /**
     * All DAR rows submitted into this cycle, across all of the
     * coordinator's OWN students only (BR-11) — scoped through the
     * coordinator relation, never a bare SubmissionCycle-only query
     * that could leak another coordinator's students if cycle IDs were
     * ever guessable.
     */
    public function show(SubmissionCycle $cycle): View
    {
        $coordinator = request()->user()->coordinator;
        abort_unless($cycle->coordinator_id === $coordinator->id, 403);

        $dars = $cycle->dailyAccomplishmentReports()
            ->with('student')
            ->orderBy('report_date')
            ->get();

        return view('coordinator.dar.review', compact('cycle', 'dars'));
    }

    public function review(
        ReviewDarRequest $request,
        DailyAccomplishmentReport $dar,
        CompletedHoursRecalculator $recalculator,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $this->authorize('review', $dar);

        if ($request->input('decision') === 'approve') {
            $dar->status = 'Approved';
            $dar->coordinator_comment = $request->input('coordinator_comment');
        } else {
            $dar->status = 'Returned';
            $dar->coordinator_comment = $request->input('coordinator_comment');
        }

        $dar->reviewed_by = $request->user()->id;
        $dar->reviewed_at = now();
        $dar->save();

        // BR-10: only an Approve changes the hours total, so only
        // recalculate on that branch — a Return doesn't touch hours.
        if ($dar->status === 'Approved') {
            $dar->loadMissing('student');
            $recalculator->recalculate($dar->student);
        }

        $auditLogger->log(
            $request->user(),
            $dar->status === 'Approved' ? 'Approve' : 'Return',
            "DAR #{$dar->id} ({$dar->report_date->toDateString()}) for student #{$dar->student_id}."
        );

        return redirect()
            ->route('coordinator.dar.review', $dar->cycle_id)
            ->with('status', "Report for {$dar->report_date->toFormattedDateString()} marked {$dar->status}.");
    }
}
