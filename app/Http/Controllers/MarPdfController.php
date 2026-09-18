<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\ReportBundleBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * pdf-forms.md §4 — official Monthly Accomplishment Report layout.
 * Reachable by either role (MarPolicy::generatePdf mirrors ::view), same
 * ownership rule as DarPdfController/WarPdfController. Generates per
 * student+month, same as WarPdfController, since a MAR row already IS
 * one month's whole document.
 *
 * Data assembly lives in ReportBundleBuilder::marMonthDocument() (shared
 * with the full-set ZIP) — this method keeps only HTTP concerns
 * (student scoping, authorization, streaming the response). Note: this
 * is the first refactor ever to move MAR's assembly (MAR has been
 * frozen all project) — zero-behavior-change proven by normalized
 * byte-diff against storage/app/sample-pdfs/baseline-mar.pdf.
 */
class MarPdfController extends Controller
{
    public function forMonth(int $student, string $month, ReportBundleBuilder $bundle): Response
    {
        $student = Student::findOrFail($student);

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();

        $doc = $bundle->marMonthDocument($student, $monthStart);

        $this->authorize('generatePdf', $doc['authorizable']);

        $pdf = Pdf::loadView($doc['view'], $doc['data'])
            // Folio 8.5×13" (612×936pt) — matches OJT-MONTHLY.docx pgSz.
            ->setPaper($doc['paper'][0], $doc['paper'][1]);

        return $pdf->stream($doc['filename']);
    }
}
