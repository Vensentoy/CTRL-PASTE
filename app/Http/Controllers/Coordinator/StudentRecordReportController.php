<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * blueprint.md §9 — Student Record Report. Full profile, company
 * assignment history (BR-12), report history (DAR/WAR/MAR submission
 * records), and approval history (who approved/returned what, when).
 * Viewable on screen from the student detail page and downloadable as a
 * PDF, same conventions as the existing DAR/WAR/MAR PDFs.
 *
 * NEW, SELF-CONTAINED CONTROLLER (this session's isolation instruction):
 * kept entirely separate from ReportController (Progress Report /
 * Monitoring Report / CSV export) so a regression in one report is easy
 * to isolate from the others, same reasoning as splitting Department
 * Summary Report into its own controller too.
 *
 * BR-11/BR-14: every query here goes through the route-bound $student
 * plus StudentPolicy::viewReports() (delegates to view(), which already
 * checks $user->coordinator->id === $student->coordinator_id) — the same
 * single authorization gate the existing Progress Report already uses
 * for the same reason (see StudentPolicy::viewReports()'s own docblock).
 *
 * APPROVAL HISTORY — HOW IT'S PULLED FROM AuditLog (per BR-14, this
 * session's explicit instruction to reuse AuditLog where it already
 * covers this): AuditLog has no direct link to a Student or to a
 * specific DAR/WAR/MAR row — its only ownership column is user_id, and
 * for Approve/Return entries user_id is the ACTING COORDINATOR's id, not
 * the student's (confirmed by reading DarReviewController::review() /
 * WarReviewController::review() / MarReviewController::review()
 * directly). Those three controllers write a consistent, human-readable
 * "...student #{id}." suffix into action_details every time, so that
 * text is the only reliable link back to a specific student for
 * Approve/Return rows. Submit rows are different — user_id there IS the
 * submitting student's own account (Student\DarController::submit() /
 * WarController::submit() / MarController::submit() all pass
 * $request->user(), the student), so those are matched by user_id
 * directly, no text matching needed.
 *
 * FLAGGED, NOT SILENTLY RELIED ON: the Approve/Return text match is a
 * LIKE query against a free-text column, not a foreign key — it only
 * works because all three review controllers happen to format that
 * suffix identically today. If a future session changes that wording,
 * this method's approval-history results for older or newer rows could
 * silently go incomplete. This is a direct consequence of AuditLog
 * having no document/student foreign key at all (see
 * AuditLogController's own docblock for the same root-cause admission
 * about coordinator scoping) — a real fix would need a schema change
 * this session wasn't scoped to make. Restated in PROJECT_STATE.md.
 */
class StudentRecordReportController extends Controller
{
    public function show(Student $student): View
    {
        $this->authorize('viewReports', $student);

        $student->load([
            'companyAssignments' => fn ($query) => $query->orderBy('start_date'),
            'dailyAccomplishmentReports' => fn ($query) => $query->orderByDesc('report_date'),
            'weeklyAccomplishmentReports' => fn ($query) => $query->orderByDesc('month_period'),
            'monthlyAccomplishmentReports' => fn ($query) => $query->orderByDesc('month_period'),
        ]);

        $approvalHistory = $this->approvalHistory($student);

        return view('coordinator.reports.student-record', compact('student', 'approvalHistory'));
    }

    public function pdf(Student $student): Response
    {
        $this->authorize('viewReports', $student);

        $student->load([
            'companyAssignments' => fn ($query) => $query->orderBy('start_date'),
            'dailyAccomplishmentReports' => fn ($query) => $query->orderByDesc('report_date'),
            'weeklyAccomplishmentReports' => fn ($query) => $query->orderByDesc('month_period'),
            'monthlyAccomplishmentReports' => fn ($query) => $query->orderByDesc('month_period'),
        ]);

        $approvalHistory = $this->approvalHistory($student);

        $pdf = Pdf::loadView('pdf.student-record', [
            'student' => $student,
            'approvalHistory' => $approvalHistory,
            'title' => "Student Record Report — {$student->fullName()}",
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Student-Record-{$student->student_id_number}.pdf");
    }

    /**
     * @return Collection<int, AuditLog>
     */
    private function approvalHistory(Student $student): Collection
    {
        return AuditLog::query()
            ->where(function ($query) use ($student) {
                $query->where('user_id', $student->user_id)
                    ->where('action_type', 'Submit');
            })
            ->orWhere(function ($query) use ($student) {
                $query->whereIn('action_type', ['Approve', 'Return'])
                    ->where('action_details', 'like', "%student #{$student->id}.%");
            })
            ->with('user.student', 'user.coordinator')
            ->orderByDesc('created_at')
            ->get();
    }
}
