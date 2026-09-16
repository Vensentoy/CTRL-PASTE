<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MonthlyAccomplishmentReport (MAR) — data-model.md: one row per student
 * per month, submitted once the month's coverage is complete. Unlike WAR
 * (four independently-reviewed week-sections per row), MAR has a single
 * status/review trail per row — the same shape as DAR's — so this
 * migration mirrors daily_accomplishment_reports_table's status/review
 * columns rather than weekly_accomplishment_reports_table's
 * four-times-over columns.
 *
 * - `cycle_id` is nullable/nullOnDelete, same reasoning as DAR/WAR: null
 *   while still Draft, set once actually submitted; a deleted cycle must
 *   never cascade-destroy a report row.
 * - `month_period` stored as the first day of the month (e.g.
 *   2026-08-01), unique per student — same convention as WAR's
 *   month_period.
 * - `activities_text`/`monthly_total_hours` are nullable at the schema
 *   level (the row is lazily created empty on first visit, same as
 *   WAR, then filled in via a save) but required by
 *   UpdateMarRequest before the student can actually submit.
 * - `monthly_total_hours` is direct numeric input here, same treatment
 *   as WAR's week{n}_hours columns — MAR has no time_started/time_ended
 *   pair to derive from the way DAR's hours_rendered does, so BR-3
 *   doesn't apply to this field. Flagged as a judgment call in
 *   PROJECT_STATE.md — pdf-forms.md's official layout actually describes
 *   the MAR body as a "Summary of Weekly Accomplishment Reports," which
 *   could imply this should be auto-computed from that student's WAR
 *   hours for the month instead of typed in fresh. data-model.md lists
 *   it as its own plain field with no such derivation rule, so it's
 *   built as direct input for now — worth confirming with LLCC's actual
 *   paper process.
 * - No soft-deletes column: matches WAR's precedent (WAR also has no
 *   destroy/delete action at all in the running app) rather than DAR's
 *   BR-9 SoftDeletes — MAR has no student-initiated delete action
 *   either in this delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_accomplishment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('cycle_id')
                ->nullable()
                ->constrained('submission_cycles')
                ->nullOnDelete();

            $table->date('month_period');
            $table->text('activities_text')->nullable();
            $table->decimal('monthly_total_hours', 6, 2)->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', ['Draft', 'Pending', 'Approved', 'Returned', 'Late'])
                ->default('Draft');
            $table->text('coordinator_comment')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'month_period']);
            $table->index(['cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_accomplishment_reports');
    }
};
