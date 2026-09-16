<?php

namespace Database\Seeders;

use App\Models\CompanyAssignment;
use App\Models\Coordinator;
use App\Models\DailyAccomplishmentReport;
use App\Models\MonthlyAccomplishmentReport;
use App\Models\OjtInformationSheet;
use App\Models\OjtWorkExperience;
use App\Models\Student;
use App\Models\SubmissionCycle;
use App\Models\User;
use App\Models\WeeklyAccomplishmentReport;
use App\Services\AuditLogger;
use App\Services\CompletedHoursRecalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL/DEV TESTING ONLY — same caveats as DevTestSeeder (BR-5: no
 * seeder should ever exist in production; this is a deliberate one-time
 * exception for manual QA).
 *
 * Run against a FRESH database:
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=Database\\Seeders\\FullFeatureTestSeeder
 *
 * All dates are computed relative to now() rather than hardcoded, so
 * this seeder stays valid no matter when you actually run it — no
 * "past cycle that's secretly in the future" bugs like the ones we hit
 * doing this by hand in tinker.
 *
 * Login for every account: password "password" (must_change_password is
 * true, so expect a forced password-change prompt on first login if
 * that flow exists yet).
 *
 * One coordinator (Maria Reyes) owns 6 students, each built to exercise
 * a different part of the system end-to-end. A second coordinator
 * (James Cruz) owns 1 separate student, purely so you can test that
 * Maria can never see or reach James's student (BR-11/BR-14).
 *
 * See the accompanying testing guide for exactly which account to use
 * for which feature.
 *
 * Setup-only additions (data pre-seeded so the *review/display* side of
 * a feature is ready to test immediately, while the matching *create*
 * form for that same feature stays a genuine live UI test elsewhere):
 *   - Ana Santos: Week 3-4 of her WAR is filled in and submitted into
 *     cycleB2, so both cycle slots exist for her month and her MAR
 *     should now be fillable (BR-8 gating) -- confirm this by viewing
 *     the UI, not by seeding a MAR row.
 *   - Juan Dela Cruz: a third CompanyAssignment (NexaByte, active) is
 *     pre-seeded on top of his existing BrightPath->CebuTech switch, so
 *     the history view already has three rows. The "switch again
 *     through the UI" step in the testing guide is now optional --
 *     that mechanic is already proven by the two switches already on
 *     his record.
 *   - An extra next-month SubmissionCycle exists for Coordinator 1, so
 *     the roster shows more than just the current open cycle. The
 *     manual "create a cycle" form is still there to try live if you
 *     want to see BR-5's deadline validation fire yourself.
 */
class FullFeatureTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $auditLogger = new AuditLogger();
        $hoursRecalc = new CompletedHoursRecalculator();

        // Two full months entirely in the past, regardless of today's
        // date — every day inside them is guaranteed to be before now(),
        // so cycle deadlines built from them never accidentally land in
        // the future no matter what day of the month this seeder runs on.
        $monthM2 = $now->copy()->subMonths(2)->startOfMonth(); // two months ago
        $monthM1 = $now->copy()->subMonth()->startOfMonth();   // one month ago

        // ================================================================
        // COORDINATOR 1 — Maria Reyes (primary test coordinator)
        // ================================================================
        $coord1User = User::create([
            'role' => 'coordinator',
            'username' => 'coord.reyes',
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'status' => 'Active',
        ]);
        $coord1 = Coordinator::create([
            'user_id' => $coord1User->id,
            'full_name' => 'Maria Reyes',
            'department_area' => 'College of Technology',
        ]);
        $auditLogger->log($coord1User, 'AccountChange', 'Coordinator account created (seed data).');

        // --- Submission cycles under Coordinator 1 ----------------------
        // Month M-2: both slots past-deadline -> used for a fully-approved
        // history (Juan) and a fully-missed history (Liza).
        $cycleA1 = SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle 1 - ' . $monthM2->format('F Y'),
            'coverage_start_date' => $monthM2->copy(),
            'coverage_end_date' => $monthM2->copy()->addDays(14),
            'deadline_date' => $monthM2->copy()->addDays(15),
        ]);
        $cycleA2 = SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle 2 - ' . $monthM2->format('F Y'),
            'coverage_start_date' => $monthM2->copy()->addDays(15),
            'coverage_end_date' => $monthM2->copy()->endOfMonth(),
            'deadline_date' => $monthM2->copy()->endOfMonth()->addDays(2),
        ]);

        // Month M-1: both slots past-deadline -> used for a mixed
        // Pending/partial month (Ana) and a Returned document (Mark).
        $cycleB1 = SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle 1 - ' . $monthM1->format('F Y'),
            'coverage_start_date' => $monthM1->copy(),
            'coverage_end_date' => $monthM1->copy()->addDays(14),
            'deadline_date' => $monthM1->copy()->addDays(15),
        ]);
        $cycleB2 = SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle 2 - ' . $monthM1->format('F Y'),
            'coverage_start_date' => $monthM1->copy()->addDays(15),
            'coverage_end_date' => $monthM1->copy()->endOfMonth(),
            'deadline_date' => $monthM1->copy()->endOfMonth()->addDays(2),
        ]);

        // Current, still-open cycle -> used for live/in-progress testing
        // (drafts, new submissions, the actual Review screen with fresh
        // Pending items you create yourself during testing).
        $cycleCurrent = SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle - ' . $now->format('F Y') . ' (open)',
            'coverage_start_date' => $now->copy()->subDays(3),
            'coverage_end_date' => $now->copy()->addDays(4),
            'deadline_date' => $now->copy()->addDays(5),
        ]);

        // A future, next-month cycle (§8 setup item). BR-5's real
        // assertion -- "there is no way to make the system
        // auto-generate one" -- is an absence to verify (no button, no
        // scheduled job, no auto-created row), which reading the roster
        // proves just as well as clicking the create form yourself. This
        // row exists so the roster already shows more than one upcoming
        // cycle; the create form itself is still there to try manually
        // if you want to see the live BR-5 validation (deadline_date
        // must be >= coverage_end_date) in action.
        SubmissionCycle::create([
            'coordinator_id' => $coord1->id,
            'cycle_name' => 'Cycle 1 - ' . $now->copy()->addMonth()->format('F Y'),
            'coverage_start_date' => $now->copy()->addMonth()->startOfMonth(),
            'coverage_end_date' => $now->copy()->addMonth()->startOfMonth()->addDays(13),
            'deadline_date' => $now->copy()->addMonth()->startOfMonth()->addDays(15),
        ]);

        // ================================================================
        // STUDENT 1 — Juan Dela Cruz: "happy path", fully approved history
        // Exercises: full Info Sheet + Section E, historized Company
        // Assignments (BR-12), a fully-approved DAR/WAR/MAR month,
        // CompletedHoursRecalculator, and an in-progress draft in the
        // current cycle.
        // ================================================================
        $juanUser = User::create([
            'role' => 'student', 'username' => 'juan.delacruz',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $juan = Student::create([
            'user_id' => $juanUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-00123', 'surname' => 'Dela Cruz', 'given_name' => 'Juan', 'middle_name' => 'Santos',
            'course' => 'BS Industrial Technology', 'major' => 'Computer Technology', 'year_section' => 'BSIT 4A',
            'ojt_start_date' => $monthM2->copy()->subDays(10)->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(2)->toDateString(),
            'required_hours' => 486, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);

        OjtWorkExperience::create([
            'ojt_information_sheet_id' => OjtInformationSheet::create([
                'student_id' => $juan->id,
                'city_address' => '123 Mabolo St., Cebu City', 'gender' => 'Male',
                'contact_number' => '0917-111-2222', 'email' => 'juan.delacruz@example.com',
                'birth_date' => '2003-05-14', 'birth_place' => 'Cebu City',
                'provincial_address' => 'Carcar City, Cebu', 'religion' => 'Roman Catholic', 'marital_status' => 'Single',
                'father_name' => 'Ricardo Dela Cruz', 'father_occupation' => 'Driver', 'father_company' => 'Cebu Transit Co.',
                'father_company_address' => 'Cebu City', 'father_contact' => '0917-333-4444',
                'mother_name' => 'Elena Dela Cruz', 'mother_occupation' => 'Vendor', 'mother_company' => 'Self-employed',
                'mother_company_address' => 'Cebu City', 'mother_contact' => '0917-555-6666',
                'guardian_name' => null, 'guardian_address' => null, 'guardian_contact' => null,
                'tertiary_school' => 'Lapu-Lapu City College', 'tertiary_address' => 'Lapu-Lapu City', 'tertiary_year_graduated' => '2026', 'tertiary_honors' => null,
                'secondary_school' => 'Cebu City National HS', 'secondary_address' => 'Cebu City', 'secondary_year_graduated' => '2019', 'secondary_honors' => "Dean's Lister",
                'primary_school' => 'Mabolo Elementary', 'primary_address' => 'Cebu City', 'primary_year_graduated' => '2015', 'primary_honors' => null,
                'height' => 170.00, 'weight' => 65.00, 'blood_type' => 'O+', 'health_problem' => null,
                'vaccination_status' => 'Booster', 'vaccine_type' => 'Pfizer', 'vaccination_place' => 'Cebu City Health Office', 'vaccination_date' => '2022-03-10',
                'health_insurance_type' => 'PhilHealth', 'health_insurance_specify' => null,
                'signed_date' => $juan->ojt_start_date,
            ])->id,
            'ojt_assignment' => 'Web Development Intern', 'position' => 'Junior Developer',
            'inclusive_start_date' => $monthM2->copy()->subDays(10)->toDateString(),
            'inclusive_end_date' => $now->copy()->addMonths(2)->toDateString(),
            'ojt_site_address' => 'IT Park, Cebu City',
        ]);

        // BR-12: closed assignment, then a new active one (company switch)
        CompanyAssignment::create([
            'student_id' => $juan->id, 'company_name' => 'BrightPath Software Inc.', 'department_area' => 'IT Department',
            'job_designation' => 'Web Developer Intern', 'mobile_number' => '0917-123-4567',
            'start_date' => $juan->ojt_start_date, 'end_date' => $monthM2->copy()->addDays(20)->toDateString(),
        ]);
        CompanyAssignment::create([
            'student_id' => $juan->id, 'company_name' => 'CebuTech Solutions', 'department_area' => 'Software Division',
            'job_designation' => 'Web Developer Intern', 'mobile_number' => '0917-123-9999',
            'start_date' => $monthM2->copy()->addDays(21)->toDateString(), 'end_date' => $now->copy()->toDateString(),
        ]);
        // A third assignment (§9 setup item): the "switch companies again"
        // action itself is a live UI test (StoreCompanyAssignmentRequest),
        // but pre-seeding this row here means the history view already has
        // three rows to display/verify from the moment you log in, instead
        // of only ever showing two until you perform the switch yourself.
        CompanyAssignment::create([
            'student_id' => $juan->id, 'company_name' => 'NexaByte Software Solutions', 'department_area' => 'Quality Assurance',
            'job_designation' => 'QA Intern', 'mobile_number' => '0917-222-3333',
            'start_date' => $now->copy()->toDateString(), 'end_date' => null,
        ]);

        // Fully-approved DAR/WAR/MAR for month M-2
        foreach ([$cycleA1, $cycleA2] as $i => $cycle) {
            foreach ([0, 1] as $dayOffset) {
                $reportDate = $cycle->coverage_start_date->copy()->addDays($dayOffset + 1);
                $dar = DailyAccomplishmentReport::create([
                    'student_id' => $juan->id, 'cycle_id' => $cycle->id, 'report_date' => $reportDate->toDateString(),
                    'activities_text' => 'Worked on assigned web development tasks and attended team standup.',
                    'time_started' => '08:00:00', 'time_ended' => '17:00:00',
                    'remarks_student' => null, 'status' => 'Approved',
                    'coordinator_comment' => null, 'reviewed_by' => $coord1User->id, 'reviewed_at' => $cycle->deadline_date,
                ]);
                $dar->hours_rendered = $dar->calculateHoursRendered();
                $dar->save();
            }
        }
        $juanWar = WeeklyAccomplishmentReport::create([
            'student_id' => $juan->id, 'month_period' => $monthM2->toDateString(),
            'week1_activities' => 'Onboarding and initial dev environment setup.', 'week1_hours' => 40.00, 'week1_status' => 'Approved', 'week1_comment' => null,
            'week2_activities' => 'Built first feature module.', 'week2_hours' => 40.00, 'week2_status' => 'Approved', 'week2_comment' => null,
            'week3_activities' => 'Bug fixes and code review participation.', 'week3_hours' => 40.00, 'week3_status' => 'Approved', 'week3_comment' => null,
            'week4_activities' => 'Feature testing and documentation.', 'week4_hours' => 40.00, 'week4_status' => 'Approved', 'week4_comment' => null,
            'cycle1_id' => $cycleA1->id, 'cycle2_id' => $cycleA2->id,
        ]);
        MonthlyAccomplishmentReport::create([
            'student_id' => $juan->id, 'cycle_id' => $cycleA2->id, 'month_period' => $monthM2->toDateString(),
            'activities_text' => 'Summary: completed onboarding, shipped first feature, participated in code reviews.',
            'monthly_total_hours' => 160.00, 'remarks' => null, 'status' => 'Approved',
            'coordinator_comment' => null, 'reviewed_by' => $coord1User->id, 'reviewed_at' => $cycleA2->deadline_date,
        ]);

        // One in-progress draft in the current open cycle
        $draftDar = DailyAccomplishmentReport::create([
            'student_id' => $juan->id, 'cycle_id' => null, 'report_date' => $now->copy()->subDay()->toDateString(),
            'activities_text' => 'Started work on the new reporting dashboard feature.',
            'time_started' => '08:00:00', 'time_ended' => '16:30:00',
            'remarks_student' => null, 'status' => 'Draft',
        ]);
        $draftDar->hours_rendered = $draftDar->calculateHoursRendered();
        $draftDar->save();

        $hoursRecalc->recalculate($juan);
        $auditLogger->log($juanUser, 'Submit', 'Submitted DAR/WAR/MAR for ' . $monthM2->format('F Y') . ' (seed data).');
        $auditLogger->log($coord1User, 'Approve', "Approved Juan Dela Cruz's " . $monthM2->format('F Y') . ' submissions (seed data).');

        // ================================================================
        // STUDENT 2 — Ana Santos: mixed Pending / spans-two-cycles
        // Exercises: the live Coordinator Review screen (Pending items
        // waiting for Approve/Return), WAR progressively filled across
        // two cycles (workflows.md edge case), no Info Sheet yet.
        // ================================================================
        $anaUser = User::create([
            'role' => 'student', 'username' => 'ana.santos',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $ana = Student::create([
            'user_id' => $anaUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-00456', 'surname' => 'Santos', 'given_name' => 'Ana', 'middle_name' => null,
            'course' => 'BS Industrial Technology', 'major' => 'Electronics Technology', 'year_section' => 'BSIT 4B',
            'ojt_start_date' => $monthM1->copy()->addDay()->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(2)->toDateString(),
            'required_hours' => 486, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        CompanyAssignment::create([
            'student_id' => $ana->id, 'company_name' => 'CebuTech Solutions', 'department_area' => 'Hardware Support',
            'job_designation' => 'Technical Support Intern', 'mobile_number' => '0918-765-4321',
            'start_date' => $ana->ojt_start_date, 'end_date' => null,
        ]);

        foreach ([0, 1] as $dayOffset) {
            $reportDate = $cycleB1->coverage_start_date->copy()->addDays($dayOffset + 1);
            $dar = DailyAccomplishmentReport::create([
                'student_id' => $ana->id, 'cycle_id' => $cycleB1->id, 'report_date' => $reportDate->toDateString(),
                'activities_text' => 'Assisted with hardware diagnostics and customer support tickets.',
                'time_started' => '08:00:00', 'time_ended' => '17:00:00',
                'remarks_student' => null, 'status' => 'Pending',
            ]);
            $dar->hours_rendered = $dar->calculateHoursRendered();
            $dar->save();
        }
        $reportDateB2 = $cycleB2->coverage_start_date->copy()->addDay();
        $darB2 = DailyAccomplishmentReport::create([
            'student_id' => $ana->id, 'cycle_id' => $cycleB2->id, 'report_date' => $reportDateB2->toDateString(),
            'activities_text' => 'Continued technical support rotation.',
            'time_started' => '08:00:00', 'time_ended' => '17:00:00',
            'remarks_student' => null, 'status' => 'Pending',
        ]);
        $darB2->hours_rendered = $darB2->calculateHoursRendered();
        $darB2->save();

        // WAR spans two cycles: Week 1-2 submitted (Pending), Week 3-4
        // filled in and submitted too (§5 setup item -- this is data
        // seeded directly since §5's real assertion is "MAR only appears
        // once both cycle slots exist," not the WAR week-editor form
        // itself, which is already covered as a UI test in §4).
        WeeklyAccomplishmentReport::create([
            'student_id' => $ana->id, 'month_period' => $monthM1->toDateString(),
            'week1_activities' => 'Hardware diagnostics training.', 'week1_hours' => 38.00, 'week1_status' => 'Pending', 'week1_comment' => null,
            'week2_activities' => 'Shadowed senior technician on service calls.', 'week2_hours' => 40.00, 'week2_status' => 'Pending', 'week2_comment' => null,
            'week3_activities' => 'Refactored the intake form validation and added unit tests.', 'week3_hours' => 40.00, 'week3_status' => 'Pending', 'week3_comment' => null,
            'week4_activities' => 'Deployed the fix to staging and documented the change for the team.', 'week4_hours' => 38.00, 'week4_status' => 'Pending', 'week4_comment' => null,
            'cycle1_id' => $cycleB1->id, 'cycle2_id' => $cycleB2->id,
        ]);
        // Both cycle slots now exist for this month -> her MAR should
        // become available to fill in (BR-8 gating, verified by viewing
        // the UI, not by seeding a MAR row here -- that stays a live test).

        $auditLogger->log($anaUser, 'Submit', 'Submitted DAR and full WAR (Weeks 1-4) for ' . $monthM1->format('F Y') . ' (seed data).');

        // ================================================================
        // STUDENT 3 — Mark Villanueva: Returned document awaiting resubmit
        // Exercises: Return-for-revision flow, and the student-side
        // "edit and resubmit a Returned report" flow.
        // ================================================================
        $markUser = User::create([
            'role' => 'student', 'username' => 'mark.villanueva',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $mark = Student::create([
            'user_id' => $markUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-00789', 'surname' => 'Villanueva', 'given_name' => 'Mark', 'middle_name' => 'Reyes',
            'course' => 'BS Industrial Technology', 'major' => 'Computer Technology', 'year_section' => 'BSIT 3A',
            'ojt_start_date' => $monthM1->copy()->addDay()->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(3)->toDateString(),
            'required_hours' => 500, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        OjtInformationSheet::create([
            'student_id' => $mark->id,
            'city_address' => '45 Banilad Rd., Cebu City', 'gender' => 'Male',
            'contact_number' => '0919-222-3333', 'email' => 'mark.villanueva@example.com',
            'birth_date' => '2004-02-20', 'birth_place' => 'Mandaue City',
            'provincial_address' => null, 'religion' => 'Roman Catholic', 'marital_status' => 'Single',
            'father_name' => 'Jose Villanueva', 'father_occupation' => 'Engineer', 'father_company' => 'ABC Construction',
            'father_company_address' => 'Cebu City', 'father_contact' => '0919-444-5555',
            'mother_name' => 'Carmen Villanueva', 'mother_occupation' => 'Teacher', 'mother_company' => 'DepEd',
            'mother_company_address' => 'Mandaue City', 'mother_contact' => '0919-666-7777',
            'guardian_name' => null, 'guardian_address' => null, 'guardian_contact' => null,
            'tertiary_school' => 'Lapu-Lapu City College', 'tertiary_address' => 'Lapu-Lapu City', 'tertiary_year_graduated' => null, 'tertiary_honors' => null,
            'secondary_school' => 'Mandaue City NHS', 'secondary_address' => 'Mandaue City', 'secondary_year_graduated' => '2021', 'secondary_honors' => null,
            'primary_school' => 'Banilad Elementary', 'primary_address' => 'Cebu City', 'primary_year_graduated' => '2017', 'primary_honors' => null,
            'height' => 168.00, 'weight' => 60.00, 'blood_type' => 'A+', 'health_problem' => null,
            'vaccination_status' => 'Second Dose', 'vaccine_type' => 'AstraZeneca', 'vaccination_place' => 'Mandaue Health Center', 'vaccination_date' => '2021-11-05',
            'health_insurance_type' => 'PhilHealth', 'health_insurance_specify' => null,
            'signed_date' => $mark->ojt_start_date,
        ]);
        CompanyAssignment::create([
            'student_id' => $mark->id, 'company_name' => 'Visayas Networks Inc.', 'department_area' => 'IT Support',
            'job_designation' => 'IT Support Intern', 'mobile_number' => '0919-888-1234',
            'start_date' => $mark->ojt_start_date, 'end_date' => null,
        ]);
        $reportDateMark = $cycleB1->coverage_start_date->copy()->addDays(2);
        $darMark = DailyAccomplishmentReport::create([
            'student_id' => $mark->id, 'cycle_id' => $cycleB1->id, 'report_date' => $reportDateMark->toDateString(),
            'activities_text' => 'Helped with network cable management.',
            'time_started' => '08:00:00', 'time_ended' => '17:00:00',
            'remarks_student' => null, 'status' => 'Returned',
            'coordinator_comment' => 'Please add more detail about which specific tasks you performed and any issues encountered.',
            'reviewed_by' => $coord1User->id, 'reviewed_at' => $cycleB1->deadline_date,
        ]);
        $darMark->hours_rendered = $darMark->calculateHoursRendered();
        $darMark->save();
        WeeklyAccomplishmentReport::create([
            'student_id' => $mark->id, 'month_period' => $monthM1->toDateString(),
            'week1_activities' => 'Network support tasks.', 'week1_hours' => 35.00, 'week1_status' => 'Returned',
            'week1_comment' => 'Hours logged look inconsistent with the daily reports for the same week -- please double check.',
            'week2_activities' => null, 'week2_hours' => null, 'week2_status' => 'Draft', 'week2_comment' => null,
            'week3_activities' => null, 'week3_hours' => null, 'week3_status' => 'Draft', 'week3_comment' => null,
            'week4_activities' => null, 'week4_hours' => null, 'week4_status' => 'Draft', 'week4_comment' => null,
            'cycle1_id' => $cycleB1->id, 'cycle2_id' => null,
        ]);
        $auditLogger->log($coord1User, 'Return', "Returned Mark Villanueva's DAR and Week 1 WAR for revision (seed data).");

        // ================================================================
        // STUDENT 4 — Liza Torres: never submitted anything (BR-6 target)
        // Exercises: the "Missed" DAR-cycle count, the missed-WAR-slot
        // and missed-MAR-month fix from the last session, and a
        // separately-flagged "Late" (submitted-but-late) DAR for
        // contrast, on a student whose ojt_start_date predates BOTH past
        // months so every past cycle is eligible against her.
        // ================================================================
        $lizaUser = User::create([
            'role' => 'student', 'username' => 'liza.torres',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $liza = Student::create([
            'user_id' => $lizaUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-00234', 'surname' => 'Torres', 'given_name' => 'Liza', 'middle_name' => null,
            'course' => 'BS Industrial Technology', 'major' => 'Electronics Technology', 'year_section' => 'BSIT 4A',
            'ojt_start_date' => $monthM2->copy()->subDays(10)->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(2)->toDateString(),
            'required_hours' => 486, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        CompanyAssignment::create([
            'student_id' => $liza->id, 'company_name' => 'Metro Electronics Corp.', 'department_area' => 'Field Service',
            'job_designation' => 'Field Technician Intern', 'mobile_number' => '0920-111-2222',
            'start_date' => $liza->ojt_start_date, 'end_date' => null,
        ]);
        // Deliberately: no DAR, no WAR, no MAR at all for months M-2 or
        // M-1 -> every one of cycleA1/A2/B1/B2 shows as Missed for her.

        // One separate DAR, explicitly submitted-but-late (distinct from
        // "never submitted"), just outside those months.
        $lateDar = DailyAccomplishmentReport::create([
            'student_id' => $liza->id, 'cycle_id' => $cycleB1->id,
            'report_date' => $cycleB1->coverage_start_date->copy()->addDays(3)->toDateString(),
            'activities_text' => 'Field service call assistance (submitted after the cycle deadline).',
            'time_started' => '09:00:00', 'time_ended' => '16:00:00',
            'remarks_student' => 'Sorry for the late submission.', 'status' => 'Late',
        ]);
        $lateDar->hours_rendered = $lateDar->calculateHoursRendered();
        $lateDar->save();

        // ================================================================
        // STUDENT 5 — Pedro Ramos: already over required hours (BR-10)
        // Exercises: the completion auto-lock, submission blocking once
        // Completed, the Coordinator "Reopen" action, and the terminal
        // "Archive" action.
        // ================================================================
        $pedroUser = User::create([
            'role' => 'student', 'username' => 'pedro.ramos',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $pedro = Student::create([
            'user_id' => $pedroUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-00999', 'surname' => 'Ramos', 'given_name' => 'Pedro', 'middle_name' => null,
            'course' => 'BS Industrial Technology', 'major' => 'Computer Technology', 'year_section' => 'BSIT 4C',
            'ojt_start_date' => $monthM1->copy()->addDay()->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(2)->toDateString(),
            // Deliberately low so a small amount of approved work exceeds it.
            'required_hours' => 40, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        OjtInformationSheet::create([
            'student_id' => $pedro->id,
            'city_address' => '78 Talamban, Cebu City', 'gender' => 'Male',
            'contact_number' => '0921-333-4444', 'email' => 'pedro.ramos@example.com',
            'birth_date' => '2003-09-01', 'birth_place' => 'Cebu City',
            'provincial_address' => null, 'religion' => null, 'marital_status' => 'Single',
            'father_name' => null, 'father_occupation' => null, 'father_company' => null, 'father_company_address' => null, 'father_contact' => null,
            'mother_name' => null, 'mother_occupation' => null, 'mother_company' => null, 'mother_company_address' => null, 'mother_contact' => null,
            'guardian_name' => 'Rosario Ramos', 'guardian_address' => 'Talamban, Cebu City', 'guardian_contact' => '0921-555-6666',
            'tertiary_school' => 'Lapu-Lapu City College', 'tertiary_address' => 'Lapu-Lapu City', 'tertiary_year_graduated' => '2026', 'tertiary_honors' => null,
            'secondary_school' => 'Talamban NHS', 'secondary_address' => 'Cebu City', 'secondary_year_graduated' => '2019', 'secondary_honors' => null,
            'primary_school' => 'Talamban Elementary', 'primary_address' => 'Cebu City', 'primary_year_graduated' => '2015', 'primary_honors' => null,
            'height' => 172.00, 'weight' => 70.00, 'blood_type' => 'B+', 'health_problem' => null,
            'vaccination_status' => 'Booster', 'vaccine_type' => 'Pfizer', 'vaccination_place' => 'Talamban Health Center', 'vaccination_date' => '2022-06-15',
            'health_insurance_type' => 'PhilHealth', 'health_insurance_specify' => null,
            'signed_date' => $pedro->ojt_start_date,
        ]);
        CompanyAssignment::create([
            'student_id' => $pedro->id, 'company_name' => 'QuickFix IT Services', 'department_area' => 'Support',
            'job_designation' => 'IT Support Intern', 'mobile_number' => '0921-777-8888',
            'start_date' => $pedro->ojt_start_date, 'end_date' => null,
        ]);
        // 5 approved 8-hour days = 40 hours, meeting/exceeding the 40 required.
        for ($d = 1; $d <= 5; $d++) {
            $reportDate = $cycleB1->coverage_start_date->copy()->addDays($d);
            $dar = DailyAccomplishmentReport::create([
                'student_id' => $pedro->id, 'cycle_id' => $cycleB1->id, 'report_date' => $reportDate->toDateString(),
                'activities_text' => 'IT support tasks, day ' . $d . '.',
                'time_started' => '08:00:00', 'time_ended' => '17:00:00',
                'remarks_student' => null, 'status' => 'Approved',
                'coordinator_comment' => null, 'reviewed_by' => $coord1User->id, 'reviewed_at' => $cycleB1->deadline_date,
            ]);
            $dar->hours_rendered = $dar->calculateHoursRendered();
            $dar->save();
        }
        $hoursRecalc->recalculate($pedro); // should flip ojt_status to Completed
        $auditLogger->log($coord1User, 'Approve', "Approved Pedro Ramos's DAR submissions, pushing him over required hours (seed data).");

        // ================================================================
        // STUDENT 6 — Grace Lim: brand-new, nothing filled in yet
        // Exercises: the full onboarding workflow from a completely
        // empty state -- forced password change, first-time Info Sheet
        // creation, first Company Assignment, first DAR draft.
        // ================================================================
        $graceUser = User::create([
            'role' => 'student', 'username' => 'grace.lim',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        Student::create([
            'user_id' => $graceUser->id, 'coordinator_id' => $coord1->id,
            'student_id_number' => '2023-01111', 'surname' => 'Lim', 'given_name' => 'Grace', 'middle_name' => null,
            'course' => 'BS Industrial Technology', 'major' => 'Electronics Technology', 'year_section' => 'BSIT 3B',
            'ojt_start_date' => $now->copy()->subDays(3)->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(4)->toDateString(),
            'required_hours' => 486, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        // Deliberately: no Info Sheet, no Company Assignment, no DAR/WAR/MAR.

        // ================================================================
        // COORDINATOR 2 — James Cruz, with his own student Rosa Fernandez
        // Exercises: BR-11/BR-14 isolation. Log in as coord.reyes and
        // confirm Rosa never appears in the roster, reports, or via a
        // direct URL to her record.
        // ================================================================
        $coord2User = User::create([
            'role' => 'coordinator', 'username' => 'coord.cruz',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $coord2 = Coordinator::create([
            'user_id' => $coord2User->id, 'full_name' => 'James Cruz', 'department_area' => 'College of Engineering',
        ]);
        $rosaUser = User::create([
            'role' => 'student', 'username' => 'rosa.fernandez',
            'password' => Hash::make('password'), 'must_change_password' => true, 'status' => 'Active',
        ]);
        $rosa = Student::create([
            'user_id' => $rosaUser->id, 'coordinator_id' => $coord2->id,
            'student_id_number' => '2023-00555', 'surname' => 'Fernandez', 'given_name' => 'Rosa', 'middle_name' => null,
            'course' => 'BS Civil Engineering', 'major' => null, 'year_section' => 'BSCE 4A',
            'ojt_start_date' => $monthM1->copy()->addDay()->toDateString(),
            'ojt_completion_date' => $now->copy()->addMonths(2)->toDateString(),
            'required_hours' => 500, 'completed_hours' => 0, 'ojt_status' => 'Ongoing',
        ]);
        CompanyAssignment::create([
            'student_id' => $rosa->id, 'company_name' => 'BuildRight Construction', 'department_area' => 'Site Engineering',
            'job_designation' => 'Civil Engineering Intern', 'mobile_number' => '0922-999-0000',
            'start_date' => $rosa->ojt_start_date, 'end_date' => null,
        ]);
        $coord2Cycle = SubmissionCycle::create([
            'coordinator_id' => $coord2->id,
            'cycle_name' => 'Cycle 1 - ' . $monthM1->format('F Y'),
            'coverage_start_date' => $monthM1->copy(), 'coverage_end_date' => $monthM1->copy()->addDays(14),
            'deadline_date' => $monthM1->copy()->addDays(15),
        ]);
        $rosaDar = DailyAccomplishmentReport::create([
            'student_id' => $rosa->id, 'cycle_id' => $coord2Cycle->id,
            'report_date' => $coord2Cycle->coverage_start_date->copy()->addDay()->toDateString(),
            'activities_text' => 'Site inspection and measurements.',
            'time_started' => '08:00:00', 'time_ended' => '17:00:00',
            'remarks_student' => null, 'status' => 'Approved',
            'coordinator_comment' => null, 'reviewed_by' => $coord2User->id, 'reviewed_at' => $coord2Cycle->deadline_date,
        ]);
        $rosaDar->hours_rendered = $rosaDar->calculateHoursRendered();
        $rosaDar->save();
        $hoursRecalc->recalculate($rosa);
        $auditLogger->log($coord2User, 'AccountChange', 'Coordinator account created (seed data).');

        $this->command?->info('Seeded: 2 coordinators, 7 students, 6 submission cycles, full DAR/WAR/MAR spread across Approved/Pending/Returned/Late/Missed states.');
        $this->command?->info('All passwords: password');
        $this->command?->table(
            ['Username', 'Role', 'Purpose'],
            [
                ['coord.reyes', 'Coordinator', 'Primary test coordinator (6 students)'],
                ['juan.delacruz', 'Student', 'Happy path: full history, Approved, BR-12 company switch'],
                ['ana.santos', 'Student', 'Pending review + WAR spanning two cycles'],
                ['mark.villanueva', 'Student', 'Returned DAR/WAR awaiting resubmission'],
                ['liza.torres', 'Student', 'Missed cycles + one Late DAR (BR-6)'],
                ['pedro.ramos', 'Student', 'Already Completed (BR-10 lock)'],
                ['grace.lim', 'Student', 'Brand new, fully empty (onboarding)'],
                ['coord.cruz', 'Coordinator', 'Isolation test coordinator (BR-11/BR-14)'],
                ['rosa.fernandez', 'Student', "Coord. Cruz's student -- must be invisible to coord.reyes"],
            ]
        );
    }
}