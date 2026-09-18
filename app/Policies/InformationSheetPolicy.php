<?php

namespace App\Policies;

use App\Models\OjtInformationSheet;
use App\Models\User;

/**
 * BR-11 — Coordinator scope, BR-14 — backend-enforced isolation.
 *
 * The OJT Information Sheet is one-time-only (data-model.md) with no
 * status workflow, so this policy only gates viewing/printing — there
 * is deliberately no update/submit/review surface here. Same ownership
 * shape as MarPolicy::view(): the owning student or the owning
 * coordinator, never anyone else.
 *
 * Registered alongside DarPolicy/WarPolicy/MarPolicy/StudentPolicy in
 * AppServiceProvider::boot().
 */
class InformationSheetPolicy
{
    public function view(User $user, OjtInformationSheet $sheet): bool
    {
        if ($user->isStudent()) {
            return $user->student?->id === $sheet->student_id;
        }

        if ($user->isCoordinator()) {
            $sheet->loadMissing('student');

            return $user->coordinator?->id === $sheet->student->coordinator_id;
        }

        return false;
    }

    /**
     * Generate the official-format PDF (pdf-forms.md §1). Either the
     * owning student or the owning coordinator may do this — same
     * ownership check as view(), mirroring MarPolicy::generatePdf().
     */
    public function generatePdf(User $user, OjtInformationSheet $sheet): bool
    {
        return $this->view($user, $sheet);
    }
}
