<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * BR-11 — Coordinator scope, BR-14 — backend-enforced isolation.
 *
 * A coordinator may only view/manage students CURRENTLY assigned to
 * them. This must hold even if they guess or are handed another
 * student's ID directly (BR-11 explicitly calls this out).
 *
 * A student may only view their own record.
 *
 * Register this in AppServiceProvider (or via #[UsePolicy] once that
 * controller exists) and call it in every Student-touching controller
 * action, e.g. `$this->authorize('view', $student);` — never rely on
 * the UI simply not showing a link to hide access.
 */
class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        if ($user->isStudent()) {
            return $user->student?->id === $student->id;
        }

        if ($user->isCoordinator()) {
            return $user->coordinator?->id === $student->coordinator_id;
        }

        return false;
    }

    public function update(User $user, Student $student): bool
    {
        // Only the owning coordinator may edit a student's record.
        // Students edit their own submissions (DAR/WAR/MAR), not their
        // own profile fields like required_hours or coordinator_id.
        return $user->isCoordinator()
            && $user->coordinator?->id === $student->coordinator_id;
    }

    /**
     * BR-10 reopen action: same ownership rule as update() — only the
     * student's current coordinator may reopen a Completed record.
     * Kept as a separate policy method (rather than reusing update())
     * so a future rule change to one action doesn't silently affect
     * the other.
     */
    public function reopen(User $user, Student $student): bool
    {
        return $user->isCoordinator()
            && $user->coordinator?->id === $student->coordinator_id;
    }

    /**
     * Workflows.md §5 step 3 — finalize/archive. Same ownership rule as
     * update()/reopen(): only the student's CURRENT coordinator may
     * archive their record. Kept as its own method (not reused from
     * update()/reopen()) for the same forward-compatibility reason —
     * a future rule change to archiving shouldn't silently ripple into
     * reassignment or reopening.
     *
     * State-eligibility (must be Completed, must not already be
     * Archived) is intentionally NOT checked here — that's the
     * controller's job (see StudentController::archive()), matching
     * how reopen()'s "already not Completed" guard also lives in the
     * controller rather than the policy. This method answers only
     * "is this coordinator allowed to touch this student at all,"
     * never "is this student in the right state right now."
     */
    public function archive(User $user, Student $student): bool
    {
        return $user->isCoordinator()
            && $user->coordinator?->id === $student->coordinator_id;
    }

    /**
     * Coordinator-assisted password reset (this session — see
     * StudentController::resetPassword() for why this exists at all).
     * Same ownership rule as every other action here. Deliberately its
     * own method rather than reusing update() — a password reset is an
     * account-credentials action, not an OJT-record edit, and keeping
     * it separate means a future rule change to one can't silently
     * affect the other, same forward-compatibility reasoning as
     * reopen()/archive() above.
     */
    public function resetPassword(User $user, Student $student): bool
    {
        return $user->isCoordinator()
            && $user->coordinator?->id === $student->coordinator_id;
    }

    /**
     * THIS SESSION — Student OJT Progress Report (blueprint.md §9),
     * on-screen and PDF. Unlike resetPassword()/reopen()/archive()
     * above, this one DOES simply delegate to view() rather than
     * duplicating the ownership check inline — a progress report is a
     * read of the same student data view() already gates, just
     * rendered differently (on-screen or as a PDF), not a distinct
     * capability with its own future evolution path. This mirrors
     * DarPolicy::generatePdf()'s existing precedent, which delegates to
     * DarPolicy::view() for the exact same reason: viewing a document
     * and generating its PDF share one ownership rule, so one is
     * defined in terms of the other instead of copy-pasted.
     */
    public function viewReports(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }
}
