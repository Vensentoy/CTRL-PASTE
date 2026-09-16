<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReassignStudentRequest;
use App\Models\Coordinator;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Coordinator student roster + reassignment (BR-1, BR-11, BR-14), the
 * BR-10 reopen action, workflows.md §5 step 3's finalize/archive action,
 * and (this session) a Coordinator-assisted password reset.
 *
 * StudentPolicy already existed with view()/update()/reopen()/archive()
 * methods shaped for exactly the roster/reassignment/reopen/archive
 * features — this session added resetPassword() to that policy (same
 * ownership rule as the others) for the new action below.
 *
 * Every query goes through Coordinator::students() (BR-1/BR-11) — never
 * a bare Student::all()/find() — so a coordinator can only ever see or
 * act on students currently assigned to them, even if handed another
 * student's ID directly. StudentPolicy is still checked per-action as a
 * second layer, matching every other controller's pattern in this app.
 *
 * AuditLog wiring: reassign() logs 'AccountChange' (moving a student's
 * coordinator link is an account-level change, not a content update);
 * reopen() logs 'Update' (it flips Student.ojt_status, a content/derived
 * status field); archive() logs 'AccountChange' (an account-lifecycle
 * field). resetPassword() (this session) also logs 'AccountChange' —
 * changing login credentials is squarely an account-level change, the
 * same kind of field reassign()/archive() already use this value for,
 * not a content/workflow-status flip the way reopen()'s 'Update' is.
 */
class StudentController extends Controller
{
    public function index(): View
    {
        $coordinator = request()->user()->coordinator;

        $students = $coordinator->students()
            ->orderBy('surname')
            ->get();

        return view('coordinator.students.index', compact('students'));
    }

    /**
     * Student detail, including read access to their full company-
     * assignment history (BR-12) — flagged as missing back when the
     * Company Switch UI shipped; this is where it lands.
     */
    public function show(Student $student): View
    {
        $this->authorize('view', $student);

        $student->load(['companyAssignments' => fn ($query) => $query->orderBy('start_date')]);
        // Needed for the Archived banner — cheap load, one-to-one, and
        // the view already reads $student->user_id indirectly via this
        // relation for nothing else, so no risk of double-loading
        // elsewhere on this page.
        $student->load('user');

        // All coordinators, for the reassignment dropdown — all
        // coordinators are equal rank (roles-and-permissions.md), so
        // listing every coordinator by name here isn't a scope leak,
        // just an operational picker for where to send this student.
        $coordinators = Coordinator::orderBy('full_name')->get();

        return view('coordinator.students.show', compact('student', 'coordinators'));
    }

    /**
     * BR-1: reassigns ONLY the current coordinator_id link. Historical
     * DAR/WAR/MAR/CompanyAssignment rows key off student_id, never
     * coordinator_id, so they're untouched by this — no separate
     * historization step is needed the way BR-12 requires for company
     * changes, since data-model.md models coordinator assignment as a
     * single "current" pointer, not a historized list of rows. Only the
     * student's CURRENT owning coordinator may do this (StudentPolicy::
     * update()) — once reassigned, the old coordinator can no longer
     * reach this student's show page, so we redirect to the roster
     * rather than back to a page they'd immediately lose access to.
     *
     * ARCHIVED GUARD — DECIDED THIS SESSION (was an open question left
     * from last session, now resolved rather than left open again):
     * reassignment is now blocked once User.status === 'Archived', the
     * same way reopen() was already blocked for Archived records. The
     * two guards use the same "record is terminal" reasoning archive()'s
     * own docblock lays out, and the show.blade.php banner comment
     * already said "nothing else on this page should look actionable
     * once it's set" — reassignment was the one action that hadn't
     * caught up to that intent yet. Concretely: an Archived record has
     * already been finalized (workflows.md §5 step 3) with no un-archive
     * path, so "moving" a finalized record to a different coordinator's
     * caseload doesn't correspond to any real operational need — there's
     * no more work for either the old or the new coordinator to do on
     * it. Unlike resetPassword() below (an account-access action kept
     * reachable regardless of Archived status), reassignment is a
     * workflow-ownership action, and workflow actions on an Archived
     * record are exactly what "terminal" is meant to rule out.
     */
    public function reassign(ReassignStudentRequest $request, Student $student, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $student);

        $student->loadMissing('user');

        if ($student->user->status === 'Archived') {
            return redirect()
                ->route('coordinator.students.show', $student)
                ->with('status', "{$student->fullName()}'s record is Archived — archiving is terminal, so reassignment is blocked too.");
        }

        $newCoordinator = Coordinator::findOrFail($request->integer('coordinator_id'));
        $oldCoordinatorName = $student->coordinator?->full_name ?? 'their previous coordinator';

        $student->coordinator_id = $newCoordinator->id;
        $student->save();

        $auditLogger->log(
            $request->user(),
            'AccountChange',
            "Reassigned {$student->fullName()} from {$oldCoordinatorName} to {$newCoordinator->full_name}."
        );

        return redirect()
            ->route('coordinator.students.index')
            ->with('status', "{$student->fullName()} reassigned from {$oldCoordinatorName} to {$newCoordinator->full_name}.");
    }

    /**
     * BR-10: manually reopens a Completed student's record, clearing
     * the completion lock that StoreDarRequest/UpdateDarRequest/
     * UpdateMarRequest/SubmitMarRequest/SubmitWarRequest/
     * UpdateWarWeekRequest all check against ojt_status. Sets ojt_status
     * back to 'Ongoing' only — completed_hours is deliberately left
     * untouched, since CompletedHoursRecalculator will naturally re-flip
     * ojt_status back to Completed on the next Approve if the underlying
     * hours still meet or exceed required_hours; this action's only job
     * is to unblock new submissions, not to erase the hours total.
     *
     * No separate ReopenStudentRequest — there's no body to validate,
     * just an authorization check, so this method carries that inline
     * the way a plain administrative action would.
     *
     * ARCHIVE INTERACTION (flagged assumption — see archive()'s docblock
     * and PROJECT_STATE.md): a record that has been archived
     * (User.status === 'Archived') is also blocked from reopening here,
     * even though it's still ojt_status === 'Completed' underneath.
     * Archive is meant to be a genuinely terminal state (no un-archive
     * path exists anywhere in this app — that's the explicit assumption
     * a prior session made, since neither business-rules.md nor
     * workflows.md defines an un-archive flow). Without this extra
     * guard, a coordinator could silently defeat that terminal-ness
     * through this unrelated action, leaving the student's User.status
     * stuck at 'Archived' while ojt_status flips back to 'Ongoing' — an
     * inconsistent, confusing combination.
     */
    public function reopen(Student $student, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('reopen', $student);

        $student->loadMissing('user');

        if ($student->user->status === 'Archived') {
            return redirect()
                ->route('coordinator.students.show', $student)
                ->with('status', "{$student->fullName()}'s record is Archived — archiving is terminal, so it can't be reopened.");
        }

        if ($student->ojt_status !== 'Completed') {
            return redirect()
                ->route('coordinator.students.show', $student)
                ->with('status', "{$student->fullName()}'s record isn't marked Completed — nothing to reopen.");
        }

        $student->ojt_status = 'Ongoing';
        $student->save();

        $auditLogger->log(
            request()->user(),
            'Update',
            "Reopened {$student->fullName()}'s OJT record (was Completed)."
        );

        return redirect()
            ->route('coordinator.students.show', $student)
            ->with('status', "{$student->fullName()}'s record reopened — new submissions are unblocked.");
    }

    /**
     * Workflows.md §5 step 3: "Coordinator finalizes/archives the
     * student's OJT record."
     *
     * WHAT IT TOUCHES: User.status, not Student.ojt_status. data-model.md
     * defines User.status as Active/Inactive/Completed/Archived and
     * explicitly notes "Completed/Archived mainly apply to Students, but
     * the column lives here since it's a User-level concept" — so this
     * is the field workflows.md's step 3 is pointing at, distinct from
     * the ojt_status field reopen()/CompletedHoursRecalculator already
     * manage. The two fields are allowed to disagree in the outgoing
     * direction only (Archived + ojt_status still 'Completed' underneath
     * is expected and fine) — see the reopen() guard above for why the
     * *reverse* drift is blocked.
     *
     * ELIGIBILITY (checked here, not in the policy, matching reopen()'s
     * pattern): only a student whose ojt_status is already 'Completed'
     * may be archived — workflows.md places this step immediately after
     * "When required hours are met, status auto-changes to Completed"
     * (§5 step 2), so archiving an Ongoing record isn't a defined flow.
     * Also blocks a no-op re-archive if User.status is already
     * 'Archived'.
     *
     * ASSUMPTION, FLAGGED EXPLICITLY (not silently decided): Archived is
     * treated as TERMINAL — there is no un-archive action anywhere in
     * this app, and the reopen() guard above exists specifically to keep
     * that true. Neither business-rules.md nor workflows.md defines an
     * un-archive path, so this is a prior session's own default, not
     * something confirmed against the skill. If LLCC's real process
     * needs a way to walk this back (e.g. an accidental archive), that's
     * a genuinely open question — see PROJECT_STATE.md's Next Steps.
     *
     * BR-11/BR-14: same ownership check as every other action here
     * (StudentPolicy::archive(), scoped through the coordinator's own
     * relation) — no request input decides which student gets touched,
     * only the route-bound $student that already passed that policy.
     *
     * AUDIT LOG TYPE — 'AccountChange', not 'Update' (decision, with
     * reasoning): AuditLogger's action_type is a DB-level enum (six
     * fixed values, confirmed against the audit_logs migration and
     * AuditLogController::ACTION_TYPES) — introducing a seventh value
     * would need a schema migration, which felt like more ceremony than
     * this one action justifies. Between the two enum values already in
     * use elsewhere in this controller: reopen() logs 'Update' because
     * it changes Student.ojt_status, a content/workflow-status field.
     * archive() changes User.status instead — an account-lifecycle
     * field, the same kind of field reassign() already logs as
     * 'AccountChange' when it changes coordinator_id. Archiving is much
     * closer in kind to reassigning (an account-level state change) than
     * to reopening (a content-status flip), so 'AccountChange' is the
     * more accurate of the two existing values, not just the more
     * convenient one.
     *
     * No separate ArchiveStudentRequest, for the same reason reopen()
     * has none — there's no body to validate, just an authorization
     * check and a state-eligibility guard.
     */
    public function archive(Student $student, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('archive', $student);

        $student->loadMissing('user');

        if ($student->user->status === 'Archived') {
            return redirect()
                ->route('coordinator.students.show', $student)
                ->with('status', "{$student->fullName()}'s record is already Archived.");
        }

        if ($student->ojt_status !== 'Completed') {
            return redirect()
                ->route('coordinator.students.show', $student)
                ->with('status', "{$student->fullName()}'s record isn't marked Completed yet — only a Completed record can be archived.");
        }

        $student->user->status = 'Archived';
        $student->user->save();

        $auditLogger->log(
            request()->user(),
            'AccountChange',
            "Archived {$student->fullName()}'s OJT record (finalized after Completion)."
        );

        return redirect()
            ->route('coordinator.students.show', $student)
            ->with('status', "{$student->fullName()}'s record has been finalized and archived. This cannot be undone.");
    }

    /**
     * Coordinator-assisted password reset (this session).
     *
     * WHY THIS EXISTS: roles-and-permissions.md's own "Session & auth
     * notes" section already documents this mechanism directly — "No
     * self-service password recovery — LAN-only deployment, no email
     * verification is available. A Coordinator must manually reset a
     * forgotten password." That's not this session's own guess the way
     * the Archive-is-terminal assumption was; it's the skill's own
     * stated design, confirmed against roles-and-permissions.md before
     * writing this method. Still worth flagging explicitly in
     * PROJECT_STATE.md, the same way every other judgment call in this
     * app is flagged, since "manually reset" is described only at the
     * concept level there — the concrete shape below (temp password
     * generated server-side, shown once in a flash message, forced
     * change on next login) is this session's own implementation of
     * that documented mechanism, not something the skill spells out
     * field-by-field.
     *
     * WHAT IT DOES: generates a random temporary password, hashes it
     * onto the student's underlying User row, and sets
     * must_change_password = true — the exact same flag data-model.md
     * already defines for a coordinator-issued account at creation time
     * (see the users table migration's own docblock), reused here rather
     * than inventing a second mechanism for the same idea. There is no
     * separate "reset password" view/form: nothing needs typing beyond
     * confirming the action, so — like reopen()/archive() — this stays a
     * single-button POST with no dedicated FormRequest.
     *
     * SHOWING THE TEMP PASSWORD: with no email column on this
     * username-only schema (confirmed against the users migration), the
     * only way to hand the new password to the Coordinator is to display
     * it once, right here, in the redirect flash. It is never written to
     * AuditLog or anywhere else in plaintext — only the bcrypt hash is
     * persisted, exactly like every other password in this app.
     *
     * NOT GATED ON ojt_status/Archived, unlike reopen()/archive(): this
     * is an account-access capability, not a workflow action, so it
     * stays reachable regardless of whether the student is Ongoing,
     * Completed, or Archived — the same reasoning that kept reassign()
     * reachable for Archived students before this session (though
     * reassign() itself is now blocked for Archived, above, for a
     * different, workflow-ownership reason). A student's account may
     * still need a working login after Archived — e.g. to view their
     * own historical, read-only records later — so restricting password
     * access here would block something that has nothing to do with
     * archiving's terminal-ness.
     *
     * BR-11/BR-14: gated by StudentPolicy::resetPassword() (new this
     * session, same ownership rule as update()/reopen()/archive()) —
     * only the student's current coordinator may do this.
     *
     * AUDIT LOG TYPE — 'AccountChange': see class docblock.
     */
    public function resetPassword(Student $student, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('resetPassword', $student);

        $student->loadMissing('user');

        // No symbols: this is read aloud or handed over on paper/in
        // person (no email to deliver it through), so it should be easy
        // to transcribe correctly. Letters + numbers only, 10 characters.
        $tempPassword = Str::password(10, symbols: false);

        $student->user->forceFill([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
        ])->save();

        $auditLogger->log(
            request()->user(),
            'AccountChange',
            "Reset {$student->fullName()}'s password (Coordinator-assisted — no email on file)."
        );

        return redirect()
            ->route('coordinator.students.show', $student)
            ->with('status', "{$student->fullName()}'s password has been reset. Temporary password: {$tempPassword}")
            ->with('newTempPassword', $tempPassword);
    }
}
