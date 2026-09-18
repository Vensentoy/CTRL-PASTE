<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\ReportBundleBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * One-click ZIP of a student's full submitted report set — every DAR
 * cycle PDF, every submitted WAR month PDF, every submitted MAR month
 * PDF (each member byte-sourced from the same ReportBundleBuilder
 * methods the single-download controllers use, so members are identical
 * to the individual downloads).
 *
 * Reachable by either role via StudentPolicy::viewReports (own student /
 * own students — the same both-roles gate the per-report PDFs enforce
 * one level down, so no new policy method was needed). Drafts are
 * excluded by the builder's per-document filters; a student with nothing
 * submitted gets a 404, mirroring the single-download controllers.
 *
 * No audit row: downloads don't log anywhere in this system today
 * (none of the *PdfControllers call AuditLogger) — kept consistent.
 */
class StudentReportBundleController extends Controller
{
    public function download(Student $student, ReportBundleBuilder $bundle): BinaryFileResponse
    {
        $this->authorize('viewReports', $student);

        $zipPath = $bundle->build($student);

        return response()
            ->download($zipPath, "Reports-{$student->student_id_number}.zip")
            ->deleteFileAfterSend(true);
    }
}
