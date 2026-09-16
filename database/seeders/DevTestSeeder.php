<?php

namespace Database\Seeders;

use App\Models\CompanyAssignment;
use App\Models\Coordinator;
use App\Models\Student;
use App\Models\SubmissionCycle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL/DEV TESTING ONLY. Do not run this against a real production
 * database, and do not wire it into anything scheduled.
 *
 * BR-5 explicitly says no seeder or scheduled job should ever
 * auto-generate SubmissionCycles in the running application — the one
 * cycle created below is a deliberate, one-time exception for exercising
 * the DAR workflow locally, not a precedent for production behavior.
 * Same reasoning applies to creating Users/Students/Coordinators here —
 * in the real app these come from a Coordinator provisioning flow that
 * doesn't exist yet, not from a seeder.
 *
 * Run with:
 *   php artisan db:seed --class=Database\\Seeders\\DevTestSeeder
 *
 * Login credentials for every account created below: password "password"
 * (must_change_password is left true, so the app should prompt a change
 * on first login if that flow exists — if it doesn't yet, you can still
 * log in and use the temp password as-is).
 */
class DevTestSeeder extends Seeder
{
    public function run(): void
    {
        // --- Coordinator -----------------------------------------------
        $coordinatorUser = User::create([
            'role' => 'coordinator',
            'username' => 'coord.reyes',
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'status' => 'Active',
        ]);

        $coordinator = Coordinator::create([
            'user_id' => $coordinatorUser->id,
            'full_name' => 'Maria Reyes',
            'department_area' => 'College of Technology',
        ]);

        // --- Students ----------------------------------------------------
        $student1User = User::create([
            'role' => 'student',
            'username' => 'juan.delacruz',
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'status' => 'Active',
        ]);

        $student1 = Student::create([
            'user_id' => $student1User->id,
            'coordinator_id' => $coordinator->id,
            'student_id_number' => '2023-00123',
            'surname' => 'Dela Cruz',
            'given_name' => 'Juan',
            'middle_name' => 'Santos',
            'course' => 'BS Industrial Technology',
            'major' => 'Computer Technology',
            'year_section' => 'BSIT 4A',
            'ojt_start_date' => now()->subWeeks(3)->toDateString(),
            'ojt_completion_date' => now()->addMonths(2)->toDateString(),
            'required_hours' => 486,
            'completed_hours' => 0,
            'ojt_status' => 'Ongoing',
        ]);

        $student2User = User::create([
            'role' => 'student',
            'username' => 'ana.santos',
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'status' => 'Active',
        ]);

        $student2 = Student::create([
            'user_id' => $student2User->id,
            'coordinator_id' => $coordinator->id,
            'student_id_number' => '2023-00456',
            'surname' => 'Santos',
            'given_name' => 'Ana',
            'middle_name' => null,
            'course' => 'BS Industrial Technology',
            'major' => 'Electronics Technology',
            'year_section' => 'BSIT 4B',
            'ojt_start_date' => now()->subWeeks(3)->toDateString(),
            'ojt_completion_date' => now()->addMonths(2)->toDateString(),
            'required_hours' => 486,
            'completed_hours' => 0,
            'ojt_status' => 'Ongoing',
        ]);

        // --- Active company assignments (BR-12: end_date null = current) -
        CompanyAssignment::create([
            'student_id' => $student1->id,
            'company_name' => 'BrightPath Software Inc.',
            'department_area' => 'IT Department',
            'job_designation' => 'Web Developer Intern',
            'mobile_number' => '0917-123-4567',
            'start_date' => $student1->ojt_start_date,
            'end_date' => null,
        ]);

        CompanyAssignment::create([
            'student_id' => $student2->id,
            'company_name' => 'CebuTech Solutions',
            'department_area' => 'Hardware Support',
            'job_designation' => 'Technical Support Intern',
            'mobile_number' => '0918-765-4321',
            'start_date' => $student2->ojt_start_date,
            'end_date' => null,
        ]);

        // --- One open SubmissionCycle so drafts have somewhere to go ----
        SubmissionCycle::create([
            'coordinator_id' => $coordinator->id,
            'cycle_name' => 'August 2026 — Cycle 2',
            'coverage_start_date' => now()->subWeek()->startOfWeek()->toDateString(),
            'coverage_end_date' => now()->addWeek()->endOfWeek()->toDateString(),
            'deadline_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->command?->info('Seeded: coordinator (coord.reyes), 2 students (juan.delacruz, ana.santos), 1 open cycle. Password for all: password');
    }
}