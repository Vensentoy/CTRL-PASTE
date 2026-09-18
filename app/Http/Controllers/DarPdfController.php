<?php

namespace App\Http\Controllers;

use App\Models\SubmissionCycle;
use App\Services\ReportBundleBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * pdf-forms.md §2 — official Daily Accomplishment Report layout.
 * Reachable by either role (DarPolicy::generatePdf mirrors ::view), so
 * a student can print their own submitted batch and a coordinator can
 * print the same for review/filing — same ownership rule as viewing.
 *
 * Deliberately generates PER CYCLE, not per arbitrary date range: a
 * cycle is the natural "this is one completed submission batch" unit,
 * and every row in it already shares one coordinator/company context,
 * which the header fields need.
 *
 * Data assembly lives in ReportBundleBuilder::darCycleDocument() (shared
 * with the full-set ZIP) — this method keeps only HTTP concerns
 * (student scoping, authorization, streaming the response).
 */
class DarPdfController extends Controller
{
    public function forCycle(SubmissionCycle $cycle, int $student, ReportBundleBuilder $bundle): Response
    {
        $student = $cycle->coordinator->students()->findOrFail($student);

        $doc = $bundle->darCycleDocument($student, $cycle);

        // Ownership check via the first row is enough here since every
        // row in the document is scoped to this one $student already —
        // DarPolicy::generatePdf() just needs any one instance shaped
        // right. Empty case is handled by the builder's abort_if above
        // instead of authorizing against a nonexistent model.
        $this->authorize('generatePdf', $doc['authorizable']);

        $pdf = Pdf::loadView($doc['view'], $doc['data'])
            // Folio 8.5×13" (612×936pt) — matches OJT-DAILY-SHEET.docx
            // pgSz, not A4. Coordinator report PDFs stay A4 (no form).
            ->setPaper($doc['paper'][0], $doc['paper'][1]);

        return $pdf->stream($doc['filename']);
    }
}
