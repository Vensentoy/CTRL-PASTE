<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInformationSheetRequest;
use App\Models\OjtInformationSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Workflows.md §1 (Onboarding) step 4 — the OJT Information Sheet is
 * filled out exactly once, ever (data-model.md). Unlike DarController/
 * WarController, there is deliberately no edit()/update() here: the
 * document has no status field and no documented revision workflow.
 * Everything is scoped through $request->user()->student, never a bare
 * OjtInformationSheet::find() — BR-11/BR-14.
 */
class InformationSheetController extends Controller
{
    /**
     * Read-only view of the student's own sheet. Redirects to the
     * create form if it doesn't exist yet, so one link works for both
     * "not started" and "already submitted" states.
     */
    public function show(): View|RedirectResponse
    {
        $student = request()->user()->student;
        $sheet = $student->informationSheet;

        if ($sheet === null) {
            return redirect()->route('student.information-sheet.create');
        }

        $sheet->load('workExperiences');

        return view('student.information-sheet.show', compact('sheet'));
    }

    public function create(): View|RedirectResponse
    {
        $student = request()->user()->student;

        // Already exists — this form only ever runs once (data-model.md).
        if ($student->informationSheet !== null) {
            return redirect()->route('student.information-sheet.show');
        }

        return view('student.information-sheet.create');
    }

    public function store(StoreInformationSheetRequest $request): RedirectResponse
    {
        $student = $request->user()->student;
        $validated = $request->validated();

        $workExperiences = $validated['work_experiences'] ?? [];
        unset($validated['work_experiences']);

        $sheet = new OjtInformationSheet($validated);
        $sheet->student_id = $student->id;
        $sheet->save();

        // Section E — repeatable, may legitimately be empty.
        foreach ($workExperiences as $experience) {
            $sheet->workExperiences()->create($experience);
        }

        return redirect()
            ->route('student.information-sheet.show')
            ->with('status', 'OJT Information Sheet submitted. This form is one-time only and cannot be edited.');
    }
}
