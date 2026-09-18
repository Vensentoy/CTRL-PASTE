<?php

namespace Tests\Feature\Workflows;

use App\Models\Coordinator;
use App\Models\DailyAccomplishmentReport;
use App\Models\Student;
use App\Models\SubmissionCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Shared factory helpers for the Phase 2 click-test suite. Every
 * workflow test builds its own data inline (models only — the stock
 * UserFactory has no Student/Coordinator rows), so the DB is fully
 * controlled per test on sqlite :memory:.
 */
abstract class WorkflowTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeCoordinator(string $username = 'coord.a', string $fullName = 'Coordinator A'): Coordinator
    {
        $user = User::create([
            'role' => 'coordinator',
            'username' => $username,
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        return Coordinator::create([
            'user_id' => $user->id,
            'full_name' => $fullName,
            'department_area' => 'College of Technology',
        ]);
    }

    protected function makeStudent(Coordinator $coordinator, string $username = 'student.a', array $attributes = []): Student
    {
        $user = User::create([
            'role' => 'student',
            'username' => $username,
            'password' => Hash::make('password'),
            'must_change_password' => false,
            'status' => 'Active',
        ]);

        return Student::create(array_merge([
            'user_id' => $user->id,
            'coordinator_id' => $coordinator->id,
            'student_id_number' => 'S'.strtoupper(uniqid()),
            'surname' => 'Doe',
            'given_name' => 'Jane',
            'middle_name' => null,
            'course' => 'BS Industrial Technology',
            'major' => 'Computer Technology',
            'year_section' => 'BSIT 4A',
            'ojt_start_date' => now()->subWeeks(2)->toDateString(),
            'ojt_completion_date' => now()->addMonths(3)->toDateString(),
            'required_hours' => 486,
            'ojt_status' => 'Ongoing',
        ], $attributes));
    }

    protected function makeCycle(Coordinator $coordinator, array $attributes = []): SubmissionCycle
    {
        return SubmissionCycle::create(array_merge([
            'coordinator_id' => $coordinator->id,
            'cycle_name' => 'Cycle '.now()->format('M Y'),
            'coverage_start_date' => now()->subWeek()->startOfWeek()->toDateString(),
            'coverage_end_date' => now()->addWeek()->endOfWeek()->toDateString(),
            'deadline_date' => now()->addDays(3)->toDateString(),
        ], $attributes));
    }

    protected function makeDar(Student $student, array $attributes = []): DailyAccomplishmentReport
    {
        // BR-3: hours_rendered is always derived server-side — the model
        // mutator computes it from the `activities` array on create, so
        // callers never set hours (or times) directly.
        return DailyAccomplishmentReport::create(array_merge([
            'student_id' => $student->id,
            'report_date' => now()->toDateString(),
            'activities' => [
                ['activity' => 'Typical site activity for this test run.', 'time_started' => '08:00', 'time_ended' => '12:00'],
            ],
            'remarks_student' => null,
            'status' => 'Draft',
        ], $attributes));
    }

    /**
     * Multi-entry DAR fixture shaped like Lester's real 6/1/26
     * (lester-reference-examples/): 4 itemized activities summing to 9h.
     */
    protected function lesterDayActivities(): array
    {
        return [
            ['activity' => 'Disassembled computers for cleaning and troubleshooting.', 'time_started' => '07:30', 'time_ended' => '10:30'],
            ['activity' => 'Reassembled the computer parts for output display.', 'time_started' => '10:30', 'time_ended' => '12:00'],
            ['activity' => 'Toured the server room at the main company branch.', 'time_started' => '12:30', 'time_ended' => '15:30'],
            ['activity' => 'Checked the server room and other rooms.', 'time_started' => '15:30', 'time_ended' => '17:00'],
        ];
    }
}