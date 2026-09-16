<?php

namespace App\Policies;

use App\Models\MonthlyAccomplishmentReport;
use App\Models\User;

/**
 * BR-11 — Coordinator scope, BR-14 — backend-enforced isolation.
 *
 * MAR has a single status field per row (data-model.md), so this
 * mirrors DarPolicy's shape directly rather than WarPolicy's per-week
 * shape — there's only one section to gate here, not four.
 *
 * Registered alongside DarPolicy/WarPolicy/StudentPolicy in
 * AppServiceProvider::boot().
 */
class MarPolicy
{
    public function view(User $user, MonthlyAccomplishmentReport $mar): bool
    {
        if ($user->isStudent()) {
            return $user->student?->id === $mar->student_id;
        }

        if ($user->isCoordinator()) {
            $mar->loadMissing('student');

            return $user->coordinator?->id === $mar->student->coordinator_id;
        }

        return false;
    }

    /**
     * A student may edit MAR content only while it's still Draft (never
     * yet submitted) or has been Returned — same resubmission rule as
     * DarPolicy::update().
     */
    public function update(User $user, MonthlyAccomplishmentReport $mar): bool
    {
        if (! ($user->isStudent() && $user->student?->id === $mar->student_id)) {
            return false;
        }

        return ($mar->cycle_id === null && $mar->status === 'Draft')
            || $mar->status === 'Returned';
    }

    /**
     * Submitting into a cycle. Same ownership rule as update, plus it
     * must not already be submitted (cycle_id still null) — matches
     * DarPolicy::submit()'s shape.
     */
    public function submit(User $user, MonthlyAccomplishmentReport $mar): bool
    {
        return $user->isStudent()
            && $user->student?->id === $mar->student_id
            && $mar->cycle_id === null;
    }

    /**
     * Approve / Return for Revision. Coordinator-only, scoped to their
     * own students (BR-11), and only on rows actually awaiting review.
     */
    public function review(User $user, MonthlyAccomplishmentReport $mar): bool
    {
        if (! $user->isCoordinator()) {
            return false;
        }

        $mar->loadMissing('student');

        return $user->coordinator?->id === $mar->student->coordinator_id
            && in_array($mar->status, ['Pending', 'Late'], true);
    }

    /**
     * Generate the official-format PDF (workflows.md §6). Either the
     * owning student or the owning coordinator may do this — same
     * ownership check as view().
     */
    public function generatePdf(User $user, MonthlyAccomplishmentReport $mar): bool
    {
        return $this->view($user, $mar);
    }
}
