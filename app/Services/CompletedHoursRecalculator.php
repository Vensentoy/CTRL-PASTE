<?php

namespace App\Services;

use App\Models\Student;

/**
 * BR-10 — Completion lock. BR-2 — per-student required hours.
 *
 * Recomputes Student::completed_hours from APPROVED report hours only,
 * and flips ojt_status to Completed once completed_hours meets or
 * exceeds required_hours. Call this after ANY review action that
 * changes a report's status to or from Approved — right now that's
 * DAR (Coordinator\DarReviewController::review()), WAR
 * (Coordinator\WarReviewController::review()) and MAR
 * (Coordinator\MarReviewController::review()).
 *
 * completed_hours = Approved DAR hours + Approved WAR week hours ONLY.
 * MAR rows are deliberately EXCLUDED (BR-2/BR-10 fix, 2026-09-16):
 * MarController::monthlyHoursFromWar() derives each MAR row's
 * monthly_total_hours as the sum of that same month's WAR
 * week1_hours..week4_hours, so adding Approved MAR rows on top of
 * Approved WAR weeks counts the same hours twice and flips students
 * to Completed at roughly half their real required hours. MAR's
 * contribution to completion is already represented via
 * approvedWarHours once those weeks are Approved.
 *
 * completed_hours is intentionally NOT reset to a lower value here if a
 * previously-Approved report gets un-approved some other way — there is
 * no "un-approve" action in workflows.md, only Approve / Return (and
 * Return only applies to Pending/Late, not already-Approved rows), so
 * that case doesn't arise via the current workflow.
 */
class CompletedHoursRecalculator
{
    public function recalculate(Student $student): Student
    {
        $approvedDarHours = $student->dailyAccomplishmentReports()
            ->where('status', 'Approved')
            ->sum('hours_rendered');

        // WAR has no single-row status — each of the four week-sections
        // is reviewed independently (BR-7), so an Approved week's hours
        // count individually rather than waiting for the whole document.
        $approvedWarHours = 0;
        foreach ([1, 2, 3, 4] as $week) {
            $approvedWarHours += $student->weeklyAccomplishmentReports()
                ->where("week{$week}_status", 'Approved')
                ->sum("week{$week}_hours");
        }

        // MAR has a single status per row (unlike WAR), same as DAR — but
        // its monthly_total_hours is 100% derived from that month's WAR
        // week hours (MarController::monthlyHoursFromWar()), not
        // independent data, so it is deliberately NOT summed here (BR-2/
        // BR-10: counting both would double-count the same hours).
        $student->completed_hours = $approvedDarHours + $approvedWarHours;

        if ($student->required_hours !== null
            && $student->completed_hours >= $student->required_hours
            && $student->ojt_status !== 'Completed') {
            $student->ojt_status = 'Completed';
        }

        $student->save();

        return $student;
    }
}
