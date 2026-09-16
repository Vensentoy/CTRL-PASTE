<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewWarRequest;
use App\Models\SubmissionCycle;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\AuditLogger;
use App\Services\CompletedHoursRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Workflows.md §4, applied to WAR week-sections. A cycle can be linked as
 * either cycle1 (Week 1–2) or cycle2 (Week 3–4) on a given WAR row (BR-8),
 * so this pulls both and lets the view work out which pair is relevant
 * per row via WeeklyAccomplishmentReport::weekPairForCycle().
 *
 * AuditLog wiring (this session): review() logs an 'Approve' or
 * 'Return' row per week-section decision, same shape as
 * DarReviewController.
 */
class WarReviewController extends Controller
{
    public function show(SubmissionCycle $cycle): View
    {
        $coordinator = request()->user()->coordinator;
        abort_unless($cycle->coordinator_id === $coordinator->id, 403);

        $wars = WeeklyAccomplishmentReport::query()
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinator->id))
            ->where(fn ($query) => $query->where('cycle1_id', $cycle->id)->orWhere('cycle2_id', $cycle->id))
            ->with('student')
            ->get();

        return view('coordinator.war.review', compact('cycle', 'wars'));
    }

    public function review(
        ReviewWarRequest $request,
        WeeklyAccomplishmentReport $war,
        CompletedHoursRecalculator $recalculator,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $week = $request->integer('week');
        $this->authorize('reviewWeek', [$war, $week]);

        $war->{"week{$week}_status"} = $request->input('decision') === 'approve' ? 'Approved' : 'Returned';
        $war->{"week{$week}_comment"} = $request->input('coordinator_comment');
        $war->save();

        // BR-10: same rule as DAR review — only an Approve changes the
        // hours total, so only recalculate on that branch.
        if ($war->{"week{$week}_status"} === 'Approved') {
            $war->loadMissing('student');
            $recalculator->recalculate($war->student);
        }

        $auditLogger->log(
            $request->user(),
            $war->{"week{$week}_status"} === 'Approved' ? 'Approve' : 'Return',
            "WAR #{$war->id} Week {$week} for student #{$war->student_id}."
        );

        // Week 1–2 always live under cycle1_id, Week 3–4 under cycle2_id
        // (BR-8) — used only to send the coordinator back to the same
        // review screen they came from.
        $cycleId = $week <= 2 ? $war->cycle1_id : $war->cycle2_id;

        return redirect()
            ->route('coordinator.war.review', $cycleId)
            ->with('status', "Week {$week} marked {$war->{"week{$week}_status"}}.");
    }
}
