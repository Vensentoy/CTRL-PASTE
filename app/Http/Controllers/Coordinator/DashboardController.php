<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\CohortAggregator;
use Illuminate\View\View;

/**
 * Coordinator landing page after login. Every query is scoped through
 * $request->user()->coordinator (BR-11) — never a bare Student::all() or
 * SubmissionCycle::all() — so a coordinator only ever sees their own
 * students and cycles here, matching the same isolation guarantee already
 * enforced in DarReviewController.
 *
 * THIS SESSION (BR-6 fix item) — the cohort-aggregation logic that used
 * to live here as two private methods (attachWarMarCounts()/
 * attachOverdueCounts()) has been extracted to App\Services\
 * CohortAggregator, so the Department Summary Report can reuse the exact
 * same counting rather than re-deriving it, and the two numbers are
 * guaranteed to agree. See CohortAggregator's docblock for the BR-6 fix
 * itself (WAR/MAR "never submitted at all" now counted, not just
 * already-stored 'Late' rows) — nothing in THIS file's own logic
 * changed, it now just calls the service.
 */
class DashboardController extends Controller
{
    public function index(CohortAggregator $cohortAggregator): View
    {
        $coordinator = request()->user()->coordinator;

        $students = $coordinator->students()
            ->withCount([
                'dailyAccomplishmentReports as pending_dar_count' => function ($query) {
                    $query->whereIn('status', ['Pending', 'Late']);
                },
            ])
            ->orderBy('surname')
            ->get();

        $cohortAggregator->attachWarMarCounts($students);
        $cohortAggregator->attachOverdueCounts($coordinator, $students);

        $openCycles = $coordinator->submissionCycles()
            ->whereDate('deadline_date', '>=', now()->toDateString())
            ->orderBy('deadline_date')
            ->get();

        // THIS SESSION (dashboard discoverability fix) -- $openCycles
        // above only ever covers cycles still accepting submissions; a
        // cycle whose deadline passed while it still had Pending DAR/
        // WAR/MAR items in it used to have no Review link anywhere on
        // this page. See CohortAggregator::cyclesNeedingReview() for the
        // full reasoning.
        $cyclesNeedingReview = $cohortAggregator->cyclesNeedingReview($coordinator);

        // Same Pending/Late definition DarReviewController::review() /
        // WarReviewController::review() / MarReviewController::review()
        // act on, just summed here for at-a-glance cohort totals.
        $totalPendingDar = $students->sum('pending_dar_count');
        $totalPendingWar = $students->sum('pending_war_count');
        $totalPendingMar = $students->sum('pending_mar_count');
        $totalOverdue = $students->sum('overdue_count');

        // BR-11: roster-wide, this coordinator's own students only.
        $completedCount = $students->where('ojt_status', 'Completed')->count();
        $ongoingCount = $students->count() - $completedCount;
        $completionRate = $students->count() > 0
            ? round(($completedCount / $students->count()) * 100, 1)
            : 0.0;

        return view('coordinator.dashboard', compact(
            'coordinator',
            'students',
            'openCycles',
            'cyclesNeedingReview',
            'totalPendingDar',
            'totalPendingWar',
            'totalPendingMar',
            'totalOverdue',
            'completedCount',
            'ongoingCount',
            'completionRate',
        ));
    }
}