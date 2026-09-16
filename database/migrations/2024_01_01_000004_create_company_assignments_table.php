<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CompanyAssignment — historized, never overwritten in place (BR-12).
 *
 * - A student can have many rows over time. `end_date` null means this is
 *   the CURRENT/active assignment.
 * - "Changing companies" means: close the old row (set end_date), then
 *   INSERT a new row. Application code must never UPDATE company_name (or
 *   any identity field) on an existing row to represent a switch — that
 *   would destroy history BR-12 requires preserving.
 * - Enforcing "only one active (end_date null) assignment per student at a
 *   time" is NOT done at the schema level here — MySQL has no native
 *   partial-unique-index support the way Postgres does. This must be
 *   enforced in the service/controller layer (e.g. inside a DB transaction
 *   that closes the old row and creates the new one atomically). Flagging
 *   this explicitly so it isn't silently assumed to be schema-guaranteed.
 * - cascadeOnDelete on student_id: if a student record is ever hard-deleted
 *   (should be rare/never per BR-9-adjacent norms elsewhere in this
 *   system), their company history goes with them. Students themselves are
 *   never expected to be hard-deleted in normal operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->string('company_name');
            $table->string('department_area')->nullable();
            $table->string('job_designation')->nullable();
            $table->string('mobile_number')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = current/active

            $table->timestamps();

            $table->index(['student_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_assignments');
    }
};
