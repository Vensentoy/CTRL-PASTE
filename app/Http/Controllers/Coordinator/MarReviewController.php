<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewMarRequest;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\SubmissionCycle;
use App\Services\AuditLogger;
use App\Services\CompletedHoursRecalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Workflows.md §4, applied to MAR. Unlike WarReviewController, a MAR row
 * has exactly one status (data-model.md), so this mirrors
 * DarReviewController's shape directly — one row, one decision per
 * review action, never a bulk approval (BR-7).
 *
 * AuditLog wiring (this session): review() logs an 'Approve' or
 * 'Return' row, same shape as DarReviewController/WarReviewController.
 */
class MarReviewController extends Controller
{
    /**
     * All MAR rows submitted into this cycle, across all of the
     * coordinator's OWN students only (BR-11) — same ownership-through-
     * relation pattern as DarReviewController::show().
     */
    public function show(SubmissionCycle $cycle): View
    {
        $coordinator = request()->user()->coordinator;
        abort_unless($cycle->coordinator_id === $coordinator->id, 403);

        $mars = MonthlyAccomplishmentReport::query()
            ->where('cycle_id', $cycle->id)
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinator->id))
            ->with('student')
            ->orderBy('month_period')
            ->get();

        return view('coordinator.mar.review', compact('cycle', 'mars'));
    }

    public function review(
        ReviewMarRequest $request,
        MonthlyAccomplishmentReport $mar,
        CompletedHoursRecalculator $recalculator,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $this->authorize('review', $mar);

        $mar->status = $request->input('decision') === 'approve' ? 'Approved' : 'Returned';
        $mar->coordinator_comment = $request->input('coordinator_comment');
        $mar->reviewed_by = $request->user()->id;
        $mar->reviewed_at = now();
        $mar->save();

        // BR-10: only an Approve changes the hours total, so only
        // recalculate on that branch — a Return doesn't touch hours.
        if ($mar->status === 'Approved') {
            $mar->loadMissing('student');
            $recalculator->recalculate($mar->student);
        }

        $auditLogger->log(
            $request->user(),
            $mar->status === 'Approved' ? 'Approve' : 'Return',
            "MAR for {$mar->month_period->format('F Y')}, student #{$mar->student_id}."
        );

        return redirect()
            ->route('coordinator.mar.review', $mar->cycle_id)
            ->with('status', "MAR for {$mar->month_period->format('F Y')} marked {$mar->status}.");
    }
}
