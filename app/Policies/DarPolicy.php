<?php

namespace App\Policies;

use App\Models\DailyAccomplishmentReport;
use App\Models\User;

/**
 * BR-11 — Coordinator scope, BR-14 — backend-enforced isolation.
 *
 * A Student may only view/edit their OWN DAR rows, and only while the
 * row is still a Draft (cycle_id null, status Draft) — once submitted
 * into a cycle, editing belongs to the review workflow, not the student.
 *
 * A Coordinator may only view/review DAR rows belonging to a student
 * CURRENTLY assigned to them (mirrors StudentPolicy's ownership check —
 * same BR-11 guarantee, applied one level down).
 *
 * Register alongside StudentPolicy in AppServiceProvider::boot():
 *   Gate::policy(DailyAccomplishmentReport::class, DarPolicy::class);
 */
class DarPolicy
{
    public function view(User $user, DailyAccomplishmentReport $dar): bool
    {
        if ($user->isStudent()) {
            return $user->student?->id === $dar->student_id;
        }

        if ($user->isCoordinator()) {
            $dar->loadMissing('student');

            return $user->coordinator?->id === $dar->student->coordinator_id;
        }

        return false;
    }

    /**
     * Create/edit a DRAFT (cycle_id still null), OR edit a document a
     * Coordinator sent back — workflows.md §4.3: "Returned documents go
     * back to the student for edits; on resubmission, status returns to
     * Pending for re-review." Any other status (Pending, Late, Approved)
     * is locked from student edits — editing after submission but
     * before review would let a student dodge a Late flag; editing
     * after Approved would contradict "coordinators annotate/decide
     * only... never directly edit student-submitted content" applying
     * symmetrically once a document is final.
     */
    public function update(User $user, DailyAccomplishmentReport $dar): bool
    {
        if (! ($user->isStudent() && $user->student?->id === $dar->student_id)) {
            return false;
        }

        return ($dar->cycle_id === null && $dar->status === 'Draft')
            || $dar->status === 'Returned';
    }

    /**
     * Deletion only applies to still-unsubmitted Drafts — a Returned
     * document must go back through edit+resubmit, not be deleted and
     * silently disappear from the coordinator's review trail.
     */
    public function delete(User $user, DailyAccomplishmentReport $dar): bool
    {
        return $user->isStudent()
            && $user->student?->id === $dar->student_id
            && $dar->cycle_id === null
            && $dar->status === 'Draft';
    }

    /**
     * Batch-submit drafts into a cycle. Same ownership rule as update,
     * checked per-row inside the controller's batch loop (see
     * Student\DarController::submit()).
     */
    public function submit(User $user, DailyAccomplishmentReport $dar): bool
    {
        return $user->isStudent()
            && $user->student?->id === $dar->student_id
            && $dar->cycle_id === null;
    }

    /**
     * Approve / Return for Revision. Coordinator-only, scoped to their
     * own students (BR-11), and only on rows that are actually awaiting
     * review — never on a still-editable Draft.
     */
    public function review(User $user, DailyAccomplishmentReport $dar): bool
    {
        if (! $user->isCoordinator()) {
            return false;
        }

        $dar->loadMissing('student');

        return $user->coordinator?->id === $dar->student->coordinator_id
            && in_array($dar->status, ['Pending', 'Late'], true);
    }

    /**
     * Generate the official-format PDF (workflows.md §6). Either the
     * owning student or the owning coordinator may do this — same
     * ownership check as view().
     */
    public function generatePdf(User $user, DailyAccomplishmentReport $dar): bool
    {
        return $this->view($user, $dar);
    }
}
