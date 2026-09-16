<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyAssignmentRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Student self-service company assignment (BR-12). Mirrors the
 * self-service pattern already used by DarController/WarController/
 * InformationSheetController — the student's own record, everything
 * scoped through $request->user()->student, never a bare
 * CompanyAssignment::find() (BR-11/BR-14).
 *
 * BR-12 is enforced here in the only place it can be: company_name (or
 * any other identity field) on an existing CompanyAssignment row is
 * NEVER updated in place. "Switching" always means closing the current
 * active row (set end_date) and inserting a brand-new row, done inside
 * a DB transaction so the two writes can't partially apply.
 *
 * One form serves two states, same pattern as the Info Sheet's single
 * create form covering "not started yet": if the student has no
 * active assignment, store() just creates the first row (nothing to
 * close). If one exists, store() closes it and creates the new one.
 *
 * AuditLog wiring (this session): store() logs an 'Update' row — a
 * company switch changes the student's operational record but isn't a
 * login/submission/review/account-identity change, so 'Update' is the
 * closest fit among data-model.md's six action_type values.
 */
class CompanyAssignmentController extends Controller
{
    /**
     * Current assignment (if any) plus full history, oldest first so
     * the timeline reads naturally top-to-bottom.
     */
    public function index(): View
    {
        $student = request()->user()->student;

        $activeCompany = $student->activeCompanyAssignment();
        $history = $student->companyAssignments()
            ->orderBy('start_date')
            ->get();

        return view('student.company.index', compact('activeCompany', 'history'));
    }

    public function create(): View
    {
        $student = request()->user()->student;

        return view('student.company.create', [
            'activeCompany' => $student->activeCompanyAssignment(),
        ]);
    }

    public function store(StoreCompanyAssignmentRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $student = $request->user()->student;
        $validated = $request->validated();

        DB::transaction(function () use ($student, $validated) {
            $active = $student->activeCompanyAssignment();

            // BR-12: never edit the existing row's company_name/etc. in
            // place — only its end_date changes, to close it out.
            if ($active) {
                $active->end_date = $validated['start_date'];
                $active->save();
            }

            $student->companyAssignments()->create([
                'company_name' => $validated['company_name'],
                'department_area' => $validated['department_area'] ?? null,
                'job_designation' => $validated['job_designation'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => null,
            ]);
        });

        $auditLogger->log(
            $request->user(),
            'Update',
            "Recorded company assignment '{$validated['company_name']}' for {$student->fullName()}."
        );

        return redirect()
            ->route('student.company.index')
            ->with('status', 'Company assignment recorded.');
    }
}
