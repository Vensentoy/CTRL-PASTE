<?php

namespace App\Services;

use App\Models\Coordinator;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\SubmissionCycle;
use App\Models\WeeklyAccomplishmentReport;
use Illuminate\Database\Eloquent\Collection;

/**
 * THIS SESSION — extracted from Coordinator\DashboardController, which
 * previously had this logic as two private methods
 * (attachWarMarCounts()/attachOverdueCounts()). Pulled out so the
 * Department Summary Report (blueprint.md §9) can reuse the exact same
 * counting instead of re-deriving it — the session brief specifically
 * asked for the dashboard tile and the report to "agree," and the only
 * way to guarantee that is one shared implementation, not two that are
 * kept in sync by hand.
 *
 * Coordinator\DashboardController now just calls this service; its own
 * method bodies are unchanged in behavior, only relocated.
 *
 * BR-6 FIX (this session, carried-over open item): attachOverdueCounts()
 * previously only counted:
 *   - DAR: cycles with literally zero DAR rows submitted into them
 *     ("Missed" cycles) — already correct, unchanged here.
 *   - WAR/MAR: rows that WERE submitted but stored as status 'Late' —
 *     this missed the case of a WAR week-pair slot or MAR month that was
 *     NEVER submitted into a past cycle at all (no row, or a row whose
 *     relevant cycle slot was never filled in).
 * This session adds that missing case for WAR/MAR, using only data
 * already in the schema (cycle dates + existing WAR/MAR rows) — no new
 * columns or tables, per the session's explicit instruction.
 *
 * HOW A PAST CYCLE MAPS TO A WAR "SLOT" (needed for the fix): BR-8 says
 * WAR's four weeks fill in as two pairs — Week 1–2 during a month's
 * FIRST Submission Cycle, Week 3–4 during its SECOND. SubmissionCycle
 * has no explicit "slot number" column, so buildMonthSlots() below
 * derives it: past cycles are grouped by the calendar month of their
 * coverage_start_date, then ordered chronologically by deadline_date —
 * the earliest in a month is slot 1, the next is slot 2.
 *
 * FLAGGED LIMITATION (same spirit as the pre-existing scope-limitation
 * docblock this replaces): a month where only ONE past cycle exists is
 * genuinely ambiguous — it could be "slot 1 happened, slot 2 hasn't been
 * created yet" (not overdue yet) or "this coordinator only runs one
 * cycle that month" (a real gap). This implementation treats a
 * single-cycle month as slot 1 only, and does NOT count a missing MAR
 * for a month until BOTH slot cycles exist and are past-deadline — the
 * conservative choice, so this fix cannot flag a false "Missed" before a
 * second cycle for that month has even been created. A true single-
 * cycle-per-month workflow would need a schema-level slot marker to be
 * counted correctly; that is a genuinely open question, not resolved
 * here (see PROJECT_STATE.md).
 *
 * The existing reassignment-timestamp gap (a student reassigned onto a
 * coordinator partway through can still have older cycles counted
 * against them) is unchanged by this fix — same limitation as before,
 * still open, see PROJECT_STATE.md.
 */
class CohortAggregator
{
    /**
     * Adds pending_war_count / pending_mar_count onto each already-loaded
     * Student in the collection. Relocated verbatim from
     * Coordinator\DashboardController::attachWarMarCounts() — no
     * behavior change.
     */
    public function attachWarMarCounts(Collection $students): void
    {
        foreach ($students as $student) {
            $warPending = 0;

            $wars = WeeklyAccomplishmentReport::where('student_id', $student->id)->get();
            foreach ($wars as $war) {
                foreach ([1, 2, 3, 4] as $week) {
                    if (in_array($war->{"week{$week}_status"}, ['Pending', 'Late'], true)) {
                        $warPending++;
                    }
                }
            }

            $marPending = MonthlyAccomplishmentReport::where('student_id', $student->id)
                ->whereIn('status', ['Pending', 'Late'])
                ->count();

            $student->pending_war_count = $warPending;
            $student->pending_mar_count = $marPending;
        }
    }

    /**
     * Adds overdue_count onto each Student — relocated from
     * Coordinator\DashboardController::attachOverdueCounts(), with the
     * BR-6 fix (see class docblock) folded in additively: the DAR
     * "Missed cycle" count and the WAR/MAR "Late" counts are unchanged;
     * missedWarSlotWeeks() and missedMarMonths() below are new terms
     * added on top.
     */
    public function attachOverdueCounts(Coordinator $coordinator, Collection $students): void
    {
        $pastCycles = $coordinator->submissionCycles()
            ->whereDate('deadline_date', '<', now()->toDateString())
            ->get(['id', 'deadline_date', 'coverage_start_date']);

        $monthSlots = $this->buildMonthSlots($pastCycles);

        foreach ($students as $student) {
            $missedDarCycles = 0;

            $eligibleCycles = $student->ojt_start_date
                ? $pastCycles->filter(fn ($cycle) => $cycle->deadline_date->gte($student->ojt_start_date))
                : $pastCycles;

            if ($eligibleCycles->isNotEmpty()) {
                $eligibleCycleIds = $eligibleCycles->pluck('id');

                $submittedIntoCycles = $student->dailyAccomplishmentReports()
                    ->whereIn('cycle_id', $eligibleCycleIds)
                    ->distinct()
                    ->pluck('cycle_id');

                $missedDarCycles = $eligibleCycleIds->diff($submittedIntoCycles)->count();
            }

            $lateWarWeeks = 0;
            $wars = WeeklyAccomplishmentReport::where('student_id', $student->id)->get();
            foreach ($wars as $war) {
                foreach ([1, 2, 3, 4] as $week) {
                    if ($war->{"week{$week}_status"} === 'Late') {
                        $lateWarWeeks++;
                    }
                }
            }

            $lateMar = MonthlyAccomplishmentReport::where('student_id', $student->id)
                ->where('status', 'Late')
                ->count();

            $eligibleMonthSlots = $student->ojt_start_date
                ? $monthSlots->filter(fn ($m) => $m['month']->gte($student->ojt_start_date->copy()->startOfMonth()))
                : $monthSlots;

            [$missedWarSlotWeeks, $missedMarMonths] = $this->missedWarAndMarCounts($student->id, $eligibleMonthSlots);

            $student->overdue_count = $missedDarCycles + $lateWarWeeks + $missedWarSlotWeeks + $lateMar + $missedMarMonths;
        }
    }

    /**
     * THIS SESSION (dashboard discoverability fix) — every cycle
     * belonging to this coordinator, past-deadline or not, that still
     * has at least one DAR/WAR-week/MAR row sitting in Pending or Late.
     *
     * Why this exists: DashboardController::index() already builds
     * $openCycles filtered to `deadline_date >= today`, and the
     * dashboard's only "Review" links come from that list. A cycle
     * whose deadline has passed drops out of $openCycles even if it
     * still has unreviewed Pending work sitting in it — a coordinator
     * can't un-submit a report, and BR-6's Late/Missed status is about
     * submission timing, not about whether review is still allowed
     * afterward, so that work must stay reachable. (The full,
     * unfiltered cycle list already exists at
     * SubmissionCycleController::index() / coordinator.cycles.index,
     * but nothing on the dashboard links to it, so a coordinator has no
     * way to discover a past cycle still needs attention unless they
     * already know that route exists.)
     *
     * Deliberately additive: $openCycles / the "Open Submission Cycles"
     * section keep their existing meaning ("what's still accepting new
     * submissions"); this is a second, separate list for "what still
     * needs a decision," and a cycle can appear in both if it's both
     * open AND has Pending items.
     *
     * BR-11: scoped through $coordinator->submissionCycles(), the same
     * ownership relation DarReviewController/WarReviewController/
     * MarReviewController already gate on — a cycle can't belong to
     * another coordinator here by construction.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\SubmissionCycle>
     */
    public function cyclesNeedingReview(Coordinator $coordinator): \Illuminate\Support\Collection
    {
        $cycles = $coordinator->submissionCycles()
            ->withCount([
                'dailyAccomplishmentReports as pending_dar_count' => function ($query) {
                    $query->whereIn('status', ['Pending', 'Late']);
                },
            ])
            ->get();

        foreach ($cycles as $cycle) {
            // WAR has no direct "belongs to this cycle" relation the way
            // DAR does (BR-8: a cycle is either a WAR's cycle1 slot,
            // its cycle2 slot, or unrelated to it) -- same lookup shape
            // WarReviewController::show() already uses.
            $pendingWar = WeeklyAccomplishmentReport::query()
                ->whereHas('student', fn ($query) => $query->where('coordinator_id', $coordinator->id))
                ->where(fn ($query) => $query->where('cycle1_id', $cycle->id)->orWhere('cycle2_id', $cycle->id))
                ->get()
                ->sum(function (WeeklyAccomplishmentReport $war) use ($cycle) {
                    $weeks = $war->cycle1_id === $cycle->id ? [1, 2] : [3, 4];

                    return collect($weeks)
                        ->filter(fn ($week) => in_array($war->{"week{$week}_status"}, ['Pending', 'Late'], true))
                        ->count();
                });

            $pendingMar = MonthlyAccomplishmentReport::where('cycle_id', $cycle->id)
                ->whereIn('status', ['Pending', 'Late'])
                ->count();

            $cycle->pending_war_count = $pendingWar;
            $cycle->pending_mar_count = $pendingMar;
            $cycle->pending_total = $cycle->pending_dar_count + $pendingWar + $pendingMar;
        }

        return $cycles
            ->filter(fn (SubmissionCycle $cycle) => $cycle->pending_total > 0)
            ->sortByDesc('deadline_date')
            ->values();
    }

    /**
     * Groups a coordinator's past cycles by calendar month (keyed off
     * coverage_start_date, the field the migration's own docblock
     * describes as the coverage/scoping field) and orders each month's
     * cycles chronologically by deadline_date so the earliest becomes
     * "slot 1" (BR-8 Weeks 1–2) and the next becomes "slot 2" (Weeks
     * 3–4). See class docblock for the single-cycle-month limitation.
     *
     * @return \Illuminate\Support\Collection<int, array{month: \Illuminate\Support\Carbon, slot1: \App\Models\SubmissionCycle|null, slot2: \App\Models\SubmissionCycle|null}>
     */
    private function buildMonthSlots(Collection $pastCycles): \Illuminate\Support\Collection
    {
        return $pastCycles
            ->groupBy(fn (SubmissionCycle $cycle) => $cycle->coverage_start_date->format('Y-m'))
            ->map(function ($cyclesInMonth) {
                $ordered = $cyclesInMonth->sortBy('deadline_date')->values();

                return [
                    'month' => $ordered->first()->coverage_start_date->copy()->startOfMonth(),
                    'slot1' => $ordered->get(0),
                    'slot2' => $ordered->get(1),
                ];
            })
            ->values();
    }

    /**
     * For one student, walks every eligible past month-slot pair and
     * counts:
     *   - missed WAR weeks: 2 per slot (1 or 2) that has a real past
     *     cycle but where that student's WAR row for the month either
     *     doesn't exist, or exists but never had that slot's cycle_id
     *     filled in (cycle1_id/cycle2_id still null) — meaning those two
     *     weeks were never submitted at all, not just submitted late.
     *   - missed MAR months: 1 per month where BOTH slot cycles exist
     *     and are past-deadline, but the student's MAR row for that
     *     month either doesn't exist or is still 'Draft' (never
     *     actually submitted).
     *
     * @return array{0:int,1:int}
     */
    private function missedWarAndMarCounts(int $studentId, \Illuminate\Support\Collection $eligibleMonthSlots): array
    {
        $missedWarSlotWeeks = 0;
        $missedMarMonths = 0;

        foreach ($eligibleMonthSlots as $slot) {
            $monthPeriod = $slot['month']->toDateString();

            $war = WeeklyAccomplishmentReport::where('student_id', $studentId)
                ->whereDate('month_period', $monthPeriod)
                ->first();

            if ($slot['slot1'] && (! $war || is_null($war->cycle1_id))) {
                $missedWarSlotWeeks += 2;
            }

            if ($slot['slot2'] && (! $war || is_null($war->cycle2_id))) {
                $missedWarSlotWeeks += 2;
            }

            // MAR: only counted once both slots for the month are real,
            // past-deadline cycles — see class docblock for why a
            // single-cycle month is deliberately left ambiguous rather
            // than flagged.
            if ($slot['slot1'] && $slot['slot2']) {
                $mar = MonthlyAccomplishmentReport::where('student_id', $studentId)
                    ->whereDate('month_period', $monthPeriod)
                    ->first();

                if (! $mar || $mar->status === 'Draft') {
                    $missedMarMonths++;
                }
            }
        }

        return [$missedWarSlotWeeks, $missedMarMonths];
    }
}