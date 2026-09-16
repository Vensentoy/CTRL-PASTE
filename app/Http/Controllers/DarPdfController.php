<?php

namespace App\Http\Controllers;

use App\Models\SubmissionCycle;
use App\Services\DarPdfGrouper;
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
 */
class DarPdfController extends Controller
{
    public function forCycle(SubmissionCycle $cycle, int $student, DarPdfGrouper $grouper): Response
    {
        $student = $cycle->coordinator->students()->findOrFail($student);

        // Ownership check via the first row is enough here since every
        // row in the query below is scoped to this one $student already
        // — DarPolicy::generatePdf() just needs any one instance shaped
        // right. Empty case is handled by abort_if below instead of
        // authorizing against a nonexistent model.
        $dars = $student->dailyAccomplishmentReports()
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', ['Pending', 'Late', 'Approved', 'Returned'])
            ->orderBy('report_date')
            ->get();

        abort_if($dars->isEmpty(), 404, 'No submitted reports found for this student in this cycle.');

        $this->authorize('generatePdf', $dars->first());

        $activeCompany = $student->activeCompanyAssignment();
        $batches = $grouper->batch($dars);

        $pdf = Pdf::loadView('pdf.dar', [
            'student' => $student,
            'company' => $activeCompany,
            'cycle' => $cycle,
            'batches' => $batches,
            'grouper' => $grouper,
        ])->setPaper('a4', 'portrait');

        $filename = "DAR-{$student->student_id_number}-{$cycle->cycle_name}.pdf";

        return $pdf->stream($filename);
    }
}
