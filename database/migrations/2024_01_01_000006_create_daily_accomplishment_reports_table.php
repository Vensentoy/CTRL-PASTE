<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DailyAccomplishmentReport (DAR) — one row per calendar day of logged
 * activity (data-model.md).
 *
 * - `cycle_id` is nullable: null while the report is still a Draft, set
 *   once the student actually submits it into a SubmissionCycle. Uses
 *   nullOnDelete so a deleted cycle doesn't cascade-destroy report rows —
 *   reports are soft-deleted only (BR-9), never lost because of something
 *   that happened to an unrelated cycle.
 * - `hours_rendered` exists as a stored column but MUST be treated as
 *   derived, not user input: application code must compute it server-side
 *   from `time_started`/`time_ended` on every write (BR-3). Never add it
 *   to a form request's validated/fillable pass-through.
 * - `report_date` is NOT validated here at the schema level (BR-4: not
 *   future, not before the student's ojt_start_date, not after their
 *   ojt_completion_date). That requires reading the related Student row,
 *   which migrations can't do — this must be enforced in a Form
 *   Request/service layer once the DAR controller exists. Flagging this
 *   explicitly so it isn't assumed to already be covered.
 * - `status` covers the full lifecycle from data-model.md: Draft, Pending,
 *   Approved, Returned, Late. Note "Late" is a status value here (matching
 *   the data model as written) even though BR-6 defines lateness purely by
 *   comparing submission time to the cycle's deadline_date — whatever
 *   service transitions a report to Pending/Late should use
 *   `SubmissionCycle::isPastDeadline()` (already built) rather than
 *   re-deriving that logic.
 * - Soft deletes only (BR-9) — `deleted_at` column added, no hard delete
 *   path should ever be wired up for this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_accomplishment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('cycle_id')
                ->nullable()
                ->constrained('submission_cycles')
                ->nullOnDelete();

            $table->date('report_date');
            $table->text('activities_text');
            $table->time('time_started');
            $table->time('time_ended');
            $table->decimal('hours_rendered', 5, 2)->default(0);
            $table->text('remarks_student')->nullable();

            $table->enum('status', ['Draft', 'Pending', 'Approved', 'Returned', 'Late'])
                ->default('Draft');
            $table->text('coordinator_comment')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'report_date']);
            $table->index(['cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_accomplishment_reports');
    }
};
