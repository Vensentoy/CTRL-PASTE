<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\DailyAccomplishmentReport;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\CohortAggregator;
use Illuminate\View\View;

/**
 * blueprint.md §9 — Department Summary Report. No super-admin tier
 * exists in this app (roles-and-permissions.md), so "department" here
 * means this coordinator's own roster (BR-11) — same scope as every
 * other report in this app, just aggregated one level up from the
 * Progress Report's per-student rows.
 *
 * NEW, SELF-CONTAINED CONTROLLER (this session's isolation instruction):
 * kept separate from ReportController and StudentRecordReportController
 * so a regression in one report stays isolated from the others.
 *
 * REUSE ACCOUNTING (this session's explicit ask, same discipline as the
 * MAR module session):
 *   - Completion rate, completed/ongoing counts, and the overdue count
 *     (BR-6) are NOT re-derived here — they come from the same
 *     App\Services\CohortAggregator that Coordinator\DashboardController
 *     now also calls, guaranteeing this report's numbers match the
 *     dashboard tile exactly, which was this session's explicit
 *     requirement.
 *   - The submission-status breakdown (Pending/Approved/Returned/Late
 *     counts per document type) is a FRESH query, not reused from
 *     ReportController::monitoring() — that method is deliberately
 *     scoped to ONE SubmissionCycle at a time (its own docblock explains
 *     why), while this report needs the opposite: all-time, cohort-wide
 *     totals across every cycle. Querying fresh here avoids forcing a
 *     single-cycle-shaped method to serve a cohort-wide need it wasn't
 *     built for.
 */
class DepartmentSummaryReportController extends Controller
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

        $completedCount = $students->where('ojt_status', 'Completed')->count();
        $ongoingCount = $students->count() - $completedCount;
        $completionRate = $students->count() > 0
            ? round(($completedCount / $students->count()) * 100, 1)
            : 0.0;
        $totalOverdue = $students->sum('overdue_count');

        $statusBreakdown = [
            'dar' => $this->darStatusBreakdown($coordinator->id),
            'war' => $this->warStatusBreakdown($coordinator->id),
            'mar' => $this->marStatusBreakdown($coordinator->id),
        ];

        return view('coordinator.reports.department-summary', compact(
            'coordinator',
            'students',
            'completedCount',
            'ongoingCount',
            'completionRate',
            'totalOverdue',
            'statusBreakdown',
        ));
    }

    /**
     * @return array<string,int>
     */
    private function darStatusBreakdown(int $coordinatorId): array
    {
        $counts = DailyAccomplishmentReport::query()
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinatorId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->fillStatuses($counts);
    }

    /**
     * WAR has no single "status per row" — each of the four week-columns
     * is its own status (BR-7), so this counts week-sections, not rows,
     * same unit ReportController::monitoring()'s WAR tally already uses,
     * just summed across all cycles instead of one.
     *
     * @return array<string,int>
     */
    private function warStatusBreakdown(int $coordinatorId): array
    {
        $counts = ['Draft' => 0, 'Pending' => 0, 'Approved' => 0, 'Returned' => 0, 'Late' => 0];

        $wars = WeeklyAccomplishmentReport::query()
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinatorId))
            ->get();

        foreach ($wars as $war) {
            foreach ([1, 2, 3, 4] as $week) {
                $status = $war->{"week{$week}_status"};
                if (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }
            }
        }

        // Draft weeks (never touched at all) aren't part of "submitted"
        // work — dropped from the returned breakdown the same way the
        // Monitoring Report's tally() only counts non-Draft weeks.
        unset($counts['Draft']);

        return $counts;
    }

    /**
     * @return array<string,int>
     */
    private function marStatusBreakdown(int $coordinatorId): array
    {
        $counts = MonthlyAccomplishmentReport::query()
            ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinatorId))
            ->where('status', '!=', 'Draft')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->fillStatuses($counts);
    }

    /**
     * @param \Illuminate\Support\Collection<string,int> $counts
     * @return array<string,int>
     */
    private function fillStatuses(\Illuminate\Support\Collection $counts): array
    {
        return [
            'Pending' => (int) ($counts['Pending'] ?? 0),
            'Approved' => (int) ($counts['Approved'] ?? 0),
            'Returned' => (int) ($counts['Returned'] ?? 0),
            'Late' => (int) ($counts['Late'] ?? 0),
        ];
    }
}
