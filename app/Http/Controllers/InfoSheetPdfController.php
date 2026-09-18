<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * pdf-forms.md §1 — official OJT Information Sheet layout.
 * Reachable by either role (InformationSheetPolicy::generatePdf mirrors
 * ::view), so a student can print their own one-time sheet and a
 * coordinator can print the same for filing — same ownership rule as
 * DarPdfController/WarPdfController/MarPdfController (BR-11/BR-14).
 *
 * Generates per student: the sheet is one row per student, ever
 * (data-model.md) — there is no cycle or month parameter here, unlike
 * the DAR/WAR/MAR PDFs.
 */
class InfoSheetPdfController extends Controller
{
    public function show(int $student): Response
    {
        $student = Student::findOrFail($student);

        $sheet = $student->informationSheet()->with('workExperiences')->firstOrFail();

        $this->authorize('generatePdf', $sheet);

        $activeCompany = $student->activeCompanyAssignment();

        $pdf = Pdf::loadView('pdf.information-sheet', [
            'student' => $student,
            'sheet' => $sheet,
            'company' => $activeCompany,
        // Folio 8.5×13" (612×936pt) — matches OJT-INFO-SHEET.docx pgSz.
        ])->setPaper([0, 0, 612, 936], 'portrait');

        $filename = "INFO-SHEET-{$student->student_id_number}.pdf";

        return $pdf->stream($filename);
    }
}
