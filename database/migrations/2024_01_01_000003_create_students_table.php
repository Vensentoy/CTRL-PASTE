<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student profile — extends User 1:1.
 *
 * - `coordinator_id` is non-nullable and RESTRICT on delete: BR-13 (no
 *   orphaned students) is enforced at the schema level, not just in a
 *   form request. A coordinator cannot be deleted while students are
 *   still assigned to them — reassign first.
 * - `coordinator_id` only ever tracks the CURRENT assignment. Reassigning
 *   a student to a different coordinator updates this column but must
 *   never touch that student's historical DAR/WAR/MAR/AuditLog rows
 *   (BR-1) — those keep pointing at student_id, not coordinator_id.
 * - `completed_hours` is derived/cached — never accept it as direct
 *   input from any request (BR-3-adjacent principle, stated explicitly
 *   for this field in data-model.md). It should only ever be written by
 *   a server-side recalculation routine once that exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('coordinator_id')
                ->constrained('coordinators')
                ->restrictOnDelete();

            $table->string('student_id_number')->unique();
            $table->string('surname');
            $table->string('given_name');
            $table->string('middle_name')->nullable();

            $table->string('course');
            $table->string('major')->nullable();
            $table->string('year_section')->nullable();

            $table->date('ojt_start_date');
            $table->date('ojt_completion_date');

            $table->unsignedInteger('required_hours');
            $table->unsignedInteger('completed_hours')->default(0);

            $table->enum('ojt_status', ['Ongoing', 'Completed'])->default('Ongoing');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
