<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SubmissionCycle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use ZipArchive;

/**
 * Full-set report bundle (one ZIP with every submitted DAR/WAR/MAR PDF).
 *
 * This is the SINGLE source of truth for per-document PDF assembly: the
 * three single-download controllers (DarPdfController / WarPdfController /
 * MarPdfController) delegate their data-gathering here, so the bundle can
 * never drift from the individual downloads. Each *Document() method
 * preserves its controller's original filters, abort ordering, paper size,
 * and filename exactly — the refactor moved code, it changed no behavior
 * (proven post-refactor by normalized byte-diffs against pre-refactor
 * baselines in storage/app/sample-pdfs/baseline-*).
 *
 * Memory discipline: documentsFor() is a generator — one rendered PDF is
 * alive at a time — and build() writes each document to a temp file and
 * adds it via ZipArchive::addFile() (disk-to-disk at close() time), so
 * peak memory stays flat no matter how many cycles/months a student
 * accumulates. Temp per-doc files are removed right after close(); the
 * caller owns the returned ZIP path (the download response deletes it
 * via deleteFileAfterSend()).
 */
class ReportBundleBuilder
{
    public function __construct(private DarPdfGrouper $grouper) {}

    /**
     * @return array{filename: string, view: string, data: array, paper: array{0: array, 1: string}, authorizable: \App\Models\DailyAccomplishmentReport}
     */
    public function darCycleDocument(Student $student, SubmissionCycle $cycle): array
    {
        $dars = $student->dailyAccomplishmentReports()
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', ['Pending', 'Late', 'Approved', 'Returned'])
            ->orderBy('report_date')
            ->get();

        abort_if($dars->isEmpty(), 404, 'No submitted reports found for this student in this cycle.');

        return [
            'filename' => "DAR-{$student->student_id_number}-{$cycle->cycle_name}.pdf",
            'view' => 'pdf.dar',
            'data' => [
                'student' => $student,
                'company' => $student->activeCompanyAssignment(),
                'cycle' => $cycle,
                'batches' => $this->grouper->batch($dars),
                'grouper' => $this->grouper,
            ],
            // Folio 8.5×13" (612×936pt) — matches OJT-DAILY-SHEET.docx pgSz.
            'paper' => [[0, 0, 612, 936], 'portrait'],
            'authorizable' => $dars->first(),
        ];
    }

    /**
     * @return array{filename: string, view: string, data: array, paper: array{0: array, 1: string}, authorizable: \App\Models\WeeklyAccomplishmentReport}
     */
    public function warMonthDocument(Student $student, string $monthStart): array
    {
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

        $totalHours = $submittedWeeks->sum(fn ($week) => (float) $war->{"week{$week}_hours"});

        return [
            'filename' => "WAR-{$student->student_id_number}-{$war->month_period->format('F-Y')}.pdf",
            'view' => 'pdf.war',
            'data' => [
                'student' => $student,
                'company' => $student->activeCompanyAssignment(),
                'war' => $war,
                'totalHours' => $totalHours,
            ],
            // Folio 8.5×13" (612×936pt) — matches OJT-WEEKLY.docx pgSz.
            'paper' => [[0, 0, 612, 936], 'portrait'],
            'authorizable' => $war,
        ];
    }

    /**
     * @return array{filename: string, view: string, data: array, paper: array{0: array, 1: string}, authorizable: \App\Models\MonthlyAccomplishmentReport}
     */
    public function marMonthDocument(Student $student, string $monthStart): array
    {
        $mar = $student->monthlyAccomplishmentReports()
            ->whereDate('month_period', $monthStart)
            ->firstOrFail();

        // Printing a still-Draft (never submitted) MAR isn't meaningful —
        // mirrors DarPdfController/WarPdfController's status filtering.
        abort_if($mar->status === 'Draft', 404, 'This MAR has not been submitted yet.');

        return [
            'filename' => "MAR-{$student->student_id_number}-{$mar->month_period->format('F-Y')}.pdf",
            'view' => 'pdf.mar',
            'data' => [
                'student' => $student,
                'company' => $student->activeCompanyAssignment(),
                'mar' => $mar,
            ],
            // Folio 8.5×13" (612×936pt) — matches OJT-MONTHLY.docx pgSz.
            'paper' => [[0, 0, 612, 936], 'portrait'],
            'authorizable' => $mar,
        ];
    }

    /**
     * Render one document descriptor to PDF bytes (Folio, like the
     * single-download controllers).
     */
    public function renderDocument(array $document): string
    {
        return Pdf::loadView($document['view'], $document['data'])
            ->setPaper($document['paper'][0], $document['paper'][1])
            ->output();
    }

    /**
     * Cycles holding this student's submitted DAR rows, oldest first.
     *
     * @return Collection<int, SubmissionCycle>
     */
    public function darCycles(Student $student): Collection
    {
        $cycleIds = $student->dailyAccomplishmentReports()
            ->whereNotNull('cycle_id')
            ->whereIn('status', ['Pending', 'Late', 'Approved', 'Returned'])
            ->distinct()
            ->pluck('cycle_id');

        return SubmissionCycle::whereIn('id', $cycleIds)
            ->orderBy('coverage_start_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Month-starts (Y-m-d) with at least one submitted WAR week, oldest first.
     *
     * @return string[]
     */
    public function warMonths(Student $student): array
    {
        return $student->weeklyAccomplishmentReports()
            ->orderBy('month_period')
            ->get()
            ->filter(fn ($war) => collect([1, 2, 3, 4])
                ->contains(fn ($week) => $war->{"week{$week}_status"} !== 'Draft'))
            ->map(fn ($war) => $war->month_period->format('Y-m-d'))
            ->values()
            ->all();
    }

    /**
     * Month-starts (Y-m-d) with a submitted (non-Draft) MAR, oldest first.
     *
     * @return string[]
     */
    public function marMonths(Student $student): array
    {
        return $student->monthlyAccomplishmentReports()
            ->where('status', '!=', 'Draft')
            ->orderBy('month_period')
            ->get()
            ->map(fn ($mar) => $mar->month_period->format('Y-m-d'))
            ->values()
            ->all();
    }

    /**
     * Every bundle document, one at a time: filename => rendered PDF bytes.
     * A generator so only one rendered PDF is ever alive in memory.
     *
     * @return \Generator<string, string>
     */
    public function documentsFor(Student $student): \Generator
    {
        foreach ($this->darCycles($student) as $cycle) {
            $doc = $this->darCycleDocument($student, $cycle);
            yield $doc['filename'] => $this->renderDocument($doc);
        }

        foreach ($this->warMonths($student) as $monthStart) {
            $doc = $this->warMonthDocument($student, $monthStart);
            yield $doc['filename'] => $this->renderDocument($doc);
        }

        foreach ($this->marMonths($student) as $monthStart) {
            $doc = $this->marMonthDocument($student, $monthStart);
            yield $doc['filename'] => $this->renderDocument($doc);
        }
    }

    /**
     * Build the ZIP incrementally on disk and return its path. The caller
     * owns (and must delete) the returned file.
     */
    public function build(Student $student): string
    {
        $tmpDir = storage_path('app/tmp/bundles');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir.DIRECTORY_SEPARATOR.'bundle-'.uniqid().'.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create the report archive.');
        }

        // addFile() reads from disk at close() time, so staged temp files
        // must survive until close — they are removed in the finally below.
        $staged = [];
        $usedNames = [];

        try {
            $count = 0;

            foreach ($this->documentsFor($student) as $filename => $bytes) {
                // Defensive dedupe: two cycles could share a cycle_name
                // (e.g. after a BR-1 reassignment across coordinators) —
                // a second identical ZIP member name would silently
                // replace the first.
                $original = $filename;
                $suffix = 2;
                while (isset($usedNames[$filename])) {
                    $filename = pathinfo($original, PATHINFO_FILENAME)." ({$suffix}).".pathinfo($original, PATHINFO_EXTENSION);
                    $suffix++;
                }
                $usedNames[$filename] = true;

                $tmp = tempnam($tmpDir, 'doc');
                file_put_contents($tmp, $bytes);
                unset($bytes); // free this PDF before rendering the next
                $zip->addFile($tmp, $filename);
                $staged[] = $tmp;
                $count++;
            }

            if ($count === 0) {
                $zip->close();
                @unlink($zipPath);
                abort(404, 'No submitted reports found for this student yet.');
            }

            $zip->close();
        } finally {
            foreach ($staged as $tmp) {
                @unlink($tmp);
            }
        }

        return $zipPath;
    }
}
