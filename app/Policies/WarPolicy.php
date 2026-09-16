<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyAccomplishmentReport;

/**
 * BR-11 — Coordinator scope, BR-14 — backend-enforced isolation, BR-7 —
 * independent per-document review, applied at the week level here since
 * a single WAR row holds four independently-statused sections.
 *
 * Register alongside DarPolicy/StudentPolicy in AppServiceProvider::boot():
 *   Gate::policy(WeeklyAccomplishmentReport::class, WarPolicy::class);
 */
class WarPolicy
{
    public function view(User $user, WeeklyAccomplishmentReport $war): bool
    {
        if ($user->isStudent()) {
            return $user->student?->id === $war->student_id;
        }

        if ($user->isCoordinator()) {
            $war->loadMissing('student');

            return $user->coordinator?->id === $war->student->coordinator_id;
        }

        return false;
    }

    /**
     * A student may edit a given week only while it's still Draft or has
     * been Returned to them — same rule as DarPolicy::update(), applied
     * per week instead of to the whole row, since BR-7 means the other
     * three weeks may be in a completely different state.
     */
    public function updateWeek(User $user, WeeklyAccomplishmentReport $war, int $week): bool
    {
        if (! ($user->isStudent() && $user->student?->id === $war->student_id)) {
            return false;
        }

        $status = $war->{"week{$week}_status"};

        return in_array($status, ['Draft', 'Returned'], true);
    }

    /**
     * Submitting a week-pair into a cycle. Same ownership rule as
     * updateWeek — the controller enforces which weeks belong to which
     * cycle slot (see WeeklyAccomplishmentReport::weekPairForCycle()).
     */
    public function submit(User $user, WeeklyAccomplishmentReport $war): bool
    {
        return $user->isStudent() && $user->student?->id === $war->student_id;
    }

    /**
     * Approve / Return a single week. Coordinator-only, scoped to their
     * own students (BR-11), and only on a week actually awaiting review.
     */
    public function reviewWeek(User $user, WeeklyAccomplishmentReport $war, int $week): bool
    {
        if (! $user->isCoordinator()) {
            return false;
        }

        $war->loadMissing('student');

        if ($user->coordinator?->id !== $war->student->coordinator_id) {
            return false;
        }

        return in_array($war->{"week{$week}_status"}, ['Pending', 'Late'], true);
    }

    /**
     * Generate the official-format PDF (workflows.md §6). Either the
     * owning student or the owning coordinator may do this — same
     * ownership check as view().
     */
    public function generatePdf(User $user, WeeklyAccomplishmentReport $war): bool
    {
        return $this->view($user, $war);
    }
}
