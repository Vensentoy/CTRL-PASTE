<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * data-model.md — OJTInformationSheet: "One-time only — one row per
 * student, ever created once, not resubmitted per cycle like DAR/WAR/MAR."
 *
 * student_id is unique (not just indexed) to enforce that one-time
 * constraint at the schema level, not only in application code — a
 * second INSERT for the same student fails the DB constraint even if a
 * future code path forgets to check first.
 *
 * College/Course/Major/Year & Section are deliberately NOT duplicated
 * here even though pdf-forms.md lists them under the printed form's
 * Section A — those already live on the Student row (data-model.md's
 * OJTInformationSheet field list doesn't include them), so the PDF
 * template pulls them from the related Student instead of storing them
 * twice. Flagged in PROJECT_STATE.md as a modeling call worth knowing
 * about, not something silently assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_information_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            // --- A. Personal Data -----------------------------------
            $table->string('city_address');
            $table->enum('gender', ['Female', 'Male']);
            $table->string('contact_number');
            $table->string('email');
            $table->date('birth_date');
            $table->string('birth_place');
            $table->string('provincial_address')->nullable();
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();

            // --- B. Family Data --------------------------------------
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_company')->nullable();
            $table->string('father_company_address')->nullable();
            $table->string('father_contact')->nullable();

            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_company')->nullable();
            $table->string('mother_company_address')->nullable();
            $table->string('mother_contact')->nullable();

            $table->string('guardian_name')->nullable();
            $table->string('guardian_address')->nullable();
            $table->string('guardian_contact')->nullable();

            // --- C. Scholastic Data (three parallel blocks) ----------
            $table->string('tertiary_school')->nullable();
            $table->string('tertiary_address')->nullable();
            $table->string('tertiary_year_graduated')->nullable();
            $table->string('tertiary_honors')->nullable();

            $table->string('secondary_school')->nullable();
            $table->string('secondary_address')->nullable();
            $table->string('secondary_year_graduated')->nullable();
            $table->string('secondary_honors')->nullable();

            $table->string('primary_school')->nullable();
            $table->string('primary_address')->nullable();
            $table->string('primary_year_graduated')->nullable();
            $table->string('primary_honors')->nullable();

            // --- D. Health Data ----------------------------------------
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('blood_type')->nullable();
            $table->string('health_problem')->nullable();
            $table->enum('vaccination_status', [
                'Unvaccinated', 'First Dose', 'Second Dose', 'Booster',
            ])->nullable();
            $table->string('vaccine_type')->nullable();
            $table->string('vaccination_place')->nullable();
            $table->date('vaccination_date')->nullable();
            $table->enum('health_insurance_type', ['PhilHealth', 'Private'])->nullable();
            $table->string('health_insurance_specify')->nullable();

            // --- Closing attestation ---------------------------------
            // Signed date only — no e-signature image/data is ever
            // captured (pdf-forms.md: signature blocks always render
            // blank for physical, handwritten signing).
            $table->date('signed_date');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_information_sheets');
    }
};
