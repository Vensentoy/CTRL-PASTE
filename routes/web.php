<?php

use App\Http\Controllers\Coordinator\DarReviewController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\DepartmentSummaryReportController;
use App\Http\Controllers\Coordinator\MarReviewController;
use App\Http\Controllers\Coordinator\ReportController as CoordinatorReportController;
use App\Http\Controllers\Coordinator\StudentController as CoordinatorStudentController;
use App\Http\Controllers\Coordinator\StudentRecordReportController;
use App\Http\Controllers\Coordinator\SubmissionCycleController;
use App\Http\Controllers\Coordinator\AuditLogController;
use App\Http\Controllers\Coordinator\WarReviewController;
use App\Http\Controllers\DarPdfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\CompanyAssignmentController;
use App\Http\Controllers\Student\DarController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\InformationSheetController;
use App\Http\Controllers\Student\MarController;
use App\Http\Controllers\Student\WarController;
use App\Http\Controllers\WarPdfController;
use App\Http\Controllers\QrEnterController;
use App\Http\Controllers\QrTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — role-scoped groups
|--------------------------------------------------------------------------
|
| Merge this into whatever routes/web.php Breeze generated for you —
| don't overwrite Breeze's own auth routes (login/logout/password) with
| this file. Everything below sits behind ['auth'] + the 'role:*'
| middleware alias (EnsureRole) — confirmed registered in bootstrap/app.php
| per PROJECT_STATE.md.
|
| 'dashboard' is now a REAL route (fixes a bug found this session:
| AuthenticatedSessionController::store() redirects to route('dashboard')
| after every login, but that name never existed before — every
| successful login was crashing with RouteNotFoundException). It sits
| outside both role groups since either role can hit it; the controller
| itself forwards to the correct role-specific dashboard.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qr/enter', [QrEnterController::class, 'enter'])->name('qr.enter');

Route::middleware(['auth', 'role:coordinator'])->get('/test-role-gate', function () {
    return 'gate passed';
});

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Student-only routes
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

        Route::get('/dar', [DarController::class, 'index'])->name('dar.index');
        Route::get('/dar/create', [DarController::class, 'create'])->name('dar.create');
        Route::post('/dar', [DarController::class, 'store'])->name('dar.store');
        Route::get('/dar/{dar}/edit', [DarController::class, 'edit'])->name('dar.edit');
        Route::put('/dar/{dar}', [DarController::class, 'update'])->name('dar.update');
        Route::delete('/dar/{dar}', [DarController::class, 'destroy'])->name('dar.destroy');
        Route::post('/dar/submit', [DarController::class, 'submit'])->name('dar.submit');

        // WAR: one document per month, four independently-tracked week-
        // sections (BR-8) — see WarController for why there's no
        // create/edit/index set the way DAR has; 'show' both finds and
        // lazily creates the current month's row.
        Route::get('/war', [WarController::class, 'show'])->name('war.show');
        Route::patch('/war/{war}/week', [WarController::class, 'updateWeek'])->name('war.week.update');
        Route::post('/war/{war}/submit', [WarController::class, 'submit'])->name('war.submit');

        // MAR (BR-2 in the user's item numbering; data-model.md shows it
        // as a single-status document like DAR, not per-week like WAR):
        // one row per student per month, lazily created on first visit
        // (mirrors WarController::show()'s pattern), edited/resubmitted
        // like a DAR draft, submitted once into a cycle (single cycle_id,
        // unlike WAR's two slots).
        Route::get('/mar', [MarController::class, 'show'])->name('mar.show');
        Route::patch('/mar/{mar}', [MarController::class, 'update'])->name('mar.update');
        Route::post('/mar/{mar}/submit', [MarController::class, 'submit'])->name('mar.submit');

        // OJT Information Sheet: one-time only (data-model.md), so
        // there is no edit/update route — just create-once and a
        // read-only show. 'show' redirects to 'create' itself if the
        // sheet doesn't exist yet, so one link on the dashboard works
        // for both states.
        Route::get('/information-sheet', [InformationSheetController::class, 'show'])->name('information-sheet.show');
        Route::get('/information-sheet/create', [InformationSheetController::class, 'create'])->name('information-sheet.create');
        Route::post('/information-sheet', [InformationSheetController::class, 'store'])->name('information-sheet.store');

        // Company Assignment (BR-12): self-service, same pattern as the
        // Info Sheet — one form covers both "first ever assignment" and
        // "switching companies" (CompanyAssignmentController decides
        // which, based on whether an active row already exists).
        Route::get('/company', [CompanyAssignmentController::class, 'index'])->name('company.index');
        Route::get('/company/create', [CompanyAssignmentController::class, 'create'])->name('company.create');
        Route::post('/company', [CompanyAssignmentController::class, 'store'])->name('company.store');
    });

    // Coordinator-only routes
    Route::middleware('role:coordinator')->prefix('coordinator')->name('coordinator.')->group(function () {
        Route::post('/qr/generate', [QrTokenController::class, 'generate'])->name('qr.generate');
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index'])->name('dashboard');

        // Coordinator student roster + reassignment (BR-1, BR-11).
        // StudentPolicy was already registered in AppServiceProvider
        // before this session (confirmed by reading the live zip) — the
        // routes/controller were the only missing piece.
        Route::get('/students', [CoordinatorStudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [CoordinatorStudentController::class, 'show'])
            ->name('students.show');
        Route::patch('/students/{student}/reassign', [CoordinatorStudentController::class, 'reassign'])
            ->name('students.reassign');
        // BR-10: manually reopen a Completed student's record.
        Route::patch('/students/{student}/reopen', [CoordinatorStudentController::class, 'reopen'])
            ->name('students.reopen');
        // Workflows.md §5 step 3: finalize/archive a Completed student's
        // record. Terminal — no un-archive route exists (see
        // StudentController::archive()'s docblock for why).
        Route::patch('/students/{student}/archive', [CoordinatorStudentController::class, 'archive'])
            ->name('students.archive');

        // Coordinator-assisted password reset (this session) — no
        // self-service reset exists (username-only schema, no email
        // column; see roles-and-permissions.md's "Session & auth notes").
        Route::patch('/students/{student}/reset-password', [CoordinatorStudentController::class, 'resetPassword'])
            ->name('students.reset-password');

        // Coordinator-facing AuditLog viewer (BR-11/BR-14 scoped in AuditLogController).
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

        // BR-5: the only place in the running app that creates a
        // SubmissionCycle (besides DevTestSeeder, which is dev-only).
        Route::get('/cycles', [SubmissionCycleController::class, 'index'])->name('cycles.index');
        Route::get('/cycles/create', [SubmissionCycleController::class, 'create'])->name('cycles.create');
        Route::post('/cycles', [SubmissionCycleController::class, 'store'])->name('cycles.store');

        Route::get('/cycles/{cycle}/review', [DarReviewController::class, 'show'])->name('dar.review');
        Route::patch('/dar/{dar}/review', [DarReviewController::class, 'review'])->name('dar.review.act');

        Route::get('/cycles/{cycle}/war-review', [WarReviewController::class, 'show'])->name('war.review');
        Route::patch('/war/{war}/review', [WarReviewController::class, 'review'])->name('war.review.act');

        Route::get('/cycles/{cycle}/mar-review', [MarReviewController::class, 'show'])->name('mar.review');
        Route::patch('/mar/{mar}/review', [MarReviewController::class, 'review'])->name('mar.review.act');

        // THIS SESSION — blueprint.md §9 Reports & Exports (scoped
        // subset). Student OJT Progress Report (roster + PDF, and a
        // single-student PDF linked from students.show), Submission
        // Monitoring Report (per cycle), and the CSV roster export.
        // Student Record Report / Department Summary Report are
        // deliberately not routed here — see PROJECT_STATE.md.
        Route::get('/reports/progress', [CoordinatorReportController::class, 'progressIndex'])
            ->name('reports.progress');
        Route::get('/reports/progress/pdf', [CoordinatorReportController::class, 'progressIndexPdf'])
            ->name('reports.progress.pdf');
        Route::get('/reports/progress/{student}/pdf', [CoordinatorReportController::class, 'progressPdf'])
            ->name('reports.progress.pdf.student');
        Route::get('/reports/monitoring/{cycle}', [CoordinatorReportController::class, 'monitoring'])
            ->name('reports.monitoring');
        Route::get('/reports/export.csv', [CoordinatorReportController::class, 'exportCsv'])
            ->name('reports.export-csv');

        // THIS SESSION — blueprint.md §9 closeout. Student Record Report
        // (self-contained controller, item 1) and Department Summary
        // Report (self-contained controller, item 2) — kept as their own
        // controllers/routes rather than folded into
        // CoordinatorReportController, per this session's isolation
        // instruction.
        Route::get('/reports/students/{student}', [StudentRecordReportController::class, 'show'])
            ->name('reports.student-record');
        Route::get('/reports/students/{student}/pdf', [StudentRecordReportController::class, 'pdf'])
            ->name('reports.student-record.pdf');
        Route::get('/reports/department-summary', [DepartmentSummaryReportController::class, 'index'])
            ->name('reports.department-summary');
    });

    // Shared — either role, ownership enforced inside the controller via
    // DarPolicy::generatePdf() / WarPolicy::generatePdf() (BR-11/BR-14),
    // not by route grouping.
    Route::get('/dar/pdf/cycle/{cycle}/student/{student}', [DarPdfController::class, 'forCycle'])
        ->name('dar.pdf');
    Route::get('/war/pdf/student/{student}/month/{month}', [WarPdfController::class, 'forMonth'])
        ->name('war.pdf');
    Route::get('/mar/pdf/student/{student}/month/{month}', [MarPdfController::class, 'forMonth'])
        ->name('mar.pdf');
});

require __DIR__.'/auth.php';
