<?php

namespace App\Services;

use App\Models\Student;

/**
 * BR-10 — Completion lock.
 *
 * Recomputes Student::completed_hours from APPROVED report hours only,
 * and flips ojt_status to Completed once completed_hours meets or
 * exceeds required_hours. Call this after ANY review action that
 * changes a report's status to or from Approved — right now that's
 * just DAR (Coordinator\DarReviewController::review()), but this is
 * deliberately the one central place to extend once WAR/MAR exist, per
 * data-model.md's note that completed_hours is derived, never
 * user-entered.
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

        // MAR has a single status per row (unlike WAR), same as DAR, so
        // this is a plain sum over Approved rows.
        $approvedMarHours = $student->monthlyAccomplishmentReports()
            ->where('status', 'Approved')
            ->sum('monthly_total_hours');

        $student->completed_hours = $approvedDarHours + $approvedWarHours + $approvedMarHours;

        if ($student->required_hours !== null
            && $student->completed_hours >= $student->required_hours
            && $student->ojt_status !== 'Completed') {
            $student->ojt_status = 'Completed';
        }

        $student->save();

        return $student;
    }
}
