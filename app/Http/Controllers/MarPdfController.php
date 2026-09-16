<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * pdf-forms.md §4 — official Monthly Accomplishment Report layout.
 * Reachable by either role (MarPolicy::generatePdf mirrors ::view), same
 * ownership rule as DarPdfController/WarPdfController. Generates per
 * student+month, same as WarPdfController, since a MAR row already IS
 * one month's whole document.
 */
class MarPdfController extends Controller
{
    public function forMonth(int $student, string $month): Response
    {
        $student = Student::findOrFail($student);

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $mar = $student->monthlyAccomplishmentReports()
            ->where('month_period', $monthStart)
            ->firstOrFail();

        // Printing a still-Draft (never submitted) MAR isn't meaningful —
        // mirrors DarPdfController/WarPdfController's status filtering.
        abort_if($mar->status === 'Draft', 404, 'This MAR has not been submitted yet.');

        $this->authorize('generatePdf', $mar);

        $activeCompany = $student->activeCompanyAssignment();

        $pdf = Pdf::loadView('pdf.mar', [
            'student' => $student,
            'company' => $activeCompany,
            'mar' => $mar,
        ])->setPaper('a4', 'portrait');

        $filename = "MAR-{$student->student_id_number}-{$mar->month_period->format('F-Y')}.pdf";

        return $pdf->stream($filename);
    }
}
