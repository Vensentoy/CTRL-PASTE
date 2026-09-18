<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\ReportBundleBuilder;
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
 *
 * Data assembly lives in ReportBundleBuilder::warMonthDocument() (shared
 * with the full-set ZIP) — this method keeps only HTTP concerns
 * (student scoping, authorization, streaming the response).
 */
class WarPdfController extends Controller
{
    public function forMonth(int $student, string $month, ReportBundleBuilder $bundle): Response
    {
        $student = Student::findOrFail($student);

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $doc = $bundle->warMonthDocument($student, $monthStart);

        $this->authorize('generatePdf', $doc['authorizable']);

        $pdf = Pdf::loadView($doc['view'], $doc['data'])
            // Folio 8.5×13" (612×936pt) — matches OJT-WEEKLY.docx pgSz.
            ->setPaper($doc['paper'][0], $doc['paper'][1]);

        return $pdf->stream($doc['filename']);
    }
}
