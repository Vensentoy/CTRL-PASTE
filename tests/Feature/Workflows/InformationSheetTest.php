<?php

namespace Tests\Feature\Workflows;

use App\Models\OjtInformationSheet;

/**
 * workflows.md §1 step 4: the OJT Information Sheet is filled out
 * exactly once, ever (data-model.md). No edit/update routes exist. The
 * one-time guarantee is pinned at three layers — no edit route, request
 * authorize(), and the schema unique constraint on student_id.
 */
class InformationSheetTest extends WorkflowTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'city_address' => '123 Legaspi St',
            'gender' => 'Female',
            'contact_number' => '09171234567',
            'email' => 'jane.doe@example.com',
            'birth_date' => '2005-01-15',
            'birth_place' => 'Naga City',
            'signed_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_show_redirects_to_create_when_sheet_does_not_exist(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.forms');

        $this->actingAs($student->user)
            ->get(route('student.information-sheet.show'))
            ->assertRedirect(route('student.information-sheet.create'));
    }

    public function test_store_creates_the_sheet_once(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.forms');

        $this->actingAs($student->user)
            ->post(route('student.information-sheet.store'), $this->validPayload())
            ->assertRedirect(route('student.information-sheet.show'));

        $this->assertDatabaseCount('ojt_information_sheets', 1);
        $sheet = OjtInformationSheet::where('student_id', $student->id)->first();
        $this->assertNotNull($sheet);
        $this->assertSame('Naga City', $sheet->birth_place);
    }

    public function test_second_submit_is_blocked_after_the_first(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.forms');

        $this->actingAs($student->user)
            ->post(route('student.information-sheet.store'), $this->validPayload())
            ->assertRedirect(route('student.information-sheet.show'));

        $this->actingAs($student->user)
            ->post(route('student.information-sheet.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('ojt_information_sheets', 1);
    }

    public function test_create_redirects_to_show_once_the_sheet_exists(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.forms');

        $sheet = OjtInformationSheet::create(array_merge(
            $this->validPayload(),
            ['student_id' => $student->id],
        ));

        $this->actingAs($student->user)
            ->get(route('student.information-sheet.create'))
            ->assertRedirect(route('student.information-sheet.show'));

        $this->actingAs($student->user)
            ->get(route('student.information-sheet.show'))
            ->assertOk()
            ->assertSee('Naga City');
    }

    public function test_info_sheet_cannot_be_touched_by_another_student(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.one');
        $other = $this->makeStudent($coordinator, 'student.two');

        OjtInformationSheet::create(array_merge(
            $this->validPayload(),
            ['student_id' => $student->id],
        ));

        $this->actingAs($other->user)
            ->get(route('student.information-sheet.show'))
            ->assertRedirect(route('student.information-sheet.create'));

        $this->assertNull($other->informationSheet);
    }
}