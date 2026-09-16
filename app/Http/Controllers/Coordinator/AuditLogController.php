<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only viewer for AuditLog (data-model.md) — the first UI on top of
 * rows that have been written since two sessions ago (Login, Submit,
 * Approve, Return, AccountChange, Update) but were only reachable via
 * direct DB queries until now.
 *
 * SCOPING (BR-11, BR-14) — AuditLog has no coordinator_id column at all;
 * its only ownership field is user_id (nullable, points at any User).
 * So "a coordinator's audit view" is built from exactly two pieces, both
 * confirmed against data-model.md:
 *   1. The coordinator's OWN rows (their own Login/Update/AccountChange
 *      entries) — user_id === their own User id.
 *   2. Their CURRENTLY-assigned students' rows — user_id IN the user_ids
 *      of $coordinator->students(), the same relation StudentController
 *      already uses for BR-11 everywhere else in this app.
 * The whereIn() list is built entirely server-side from the
 * authenticated coordinator's own relation — never from client input —
 * so this holds even if someone tampers with the request (BR-14).
 *
 * FLAGGED, not solved here (see PROJECT_STATE.md open items): because
 * AuditLog rows don't snapshot which coordinator was "current" at the
 * moment the row was written, reassigning a student moves that
 * student's PAST audit rows to the new coordinator's view immediately —
 * there is no way to reconstruct "who saw this at the time" without a
 * schema change. Scoping by current assignment here is consistent with
 * every other scoped query in this app; it just means this edge case
 * needs a conscious decision, not a silent one.
 */
class AuditLogController extends Controller
{
    /**
     * data-model.md's fixed action_type set — same six values as the
     * audit_logs migration's enum column. Kept here (not re-read from
     * the DB) purely to drive the filter dropdown's option list.
     */
    private const ACTION_TYPES = [
        'Login', 'Submit', 'Approve', 'Return', 'Update', 'AccountChange',
    ];

    public function index(Request $request): View
    {
        $coordinator = $request->user()->coordinator;

        // Currently-assigned students' underlying User ids (BR-1/BR-11),
        // plus the coordinator's own User id — see class docblock.
        $userIds = $coordinator->students()
            ->pluck('user_id')
            ->push($request->user()->id);

        $query = AuditLog::query()
            ->whereIn('user_id', $userIds)
            ->with('user.student', 'user.coordinator')
            ->latest('created_at');

        // Optional filter — validated against the fixed set rather than
        // passed through raw, so an unexpected value can't silently
        // produce an empty/broken query.
        $selectedType = $request->string('action_type')->toString();
        if (in_array($selectedType, self::ACTION_TYPES, true)) {
            $query->where('action_type', $selectedType);
        } else {
            $selectedType = null;
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('coordinator.audit-log.index', [
            'logs' => $logs,
            'actionTypes' => self::ACTION_TYPES,
            'selectedType' => $selectedType,
        ]);
    }
}
