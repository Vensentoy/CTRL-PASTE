<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\WeeklyAccomplishmentReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * pdf-forms.md §3 — official Weekly Accomplishment Report layout.
 * Reachable by either role (WarPolicy::generatePdf mirrors ::view), same
 * ownership rule as DarPdfController.
 *
 * Unlike DAR, WAR isn't scoped per-cycle here — one WAR row already IS
 * one month's whole document (BR-8), so this generates per student+month
 * instead of per cycle+student. A given month's document may cover only
 * Week 1–2, only Week 3–4, or all four weeks, depending on how far the
 * student has actually submitted — the view renders whichever weeks
 * aren't still Draft and marks the rest as not yet submitted, rather
 * than requiring the whole month to be complete before anything can
 * print.
 */
class WarPdfController extends Controller
{
    public function forMonth(int $student, string $month): Response
    {
        $student = Student::findOrFail($student);

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $war = $student->weeklyAccomplishmentReports()
            ->whereDate('month_period', $monthStart)
            ->firstOrFail();

        // At least one week must have moved past Draft — printing a
        // document where every section is still blank/unsubmitted isn't
        // meaningful. Mirrors DarPdfController's status filter, applied
        // per week instead of per row.
        $submittedWeeks = collect([1, 2, 3, 4])
            ->filter(fn ($week) => $war->{"week{$week}_status"} !== 'Draft');

        abort_if($submittedWeeks->isEmpty(), 404, 'No submitted weeks found for this student in this month.');

        $this->authorize('generatePdf', $war);

        $activeCompany = $student->activeCompanyAssignment();

        $totalHours = $submittedWeeks->sum(fn ($week) => (float) $war->{"week{$week}_hours"});

        $pdf = Pdf::loadView('pdf.war', [
            'student' => $student,
            'company' => $activeCompany,
            'war' => $war,
            'totalHours' => $totalHours,
        ])->setPaper('a4', 'portrait');

        $filename = "WAR-{$student->student_id_number}-{$war->month_period->format('F-Y')}.pdf";

        return $pdf->stream($filename);
    }
}
