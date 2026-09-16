<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeeklyAccomplishmentReport (WAR) — data-model.md is explicit that this
 * is ONE document per student per month containing four week-sections,
 * NOT four separate weekly rows (BR-8 depends on that). Week 1–2 fill in
 * during the month's first Submission Cycle, Week 3–4 during its second.
 *
 * - `month_period` identifies the month this document covers (stored as
 *   the first day of the month, e.g. 2026-08-01) — unique per student.
 * - Each week{n}_status is reviewed independently (BR-7) — approving
 *   Week 1 must never auto-approve or block review of Week 2/3/4. This is
 *   why each week gets its own status/comment column instead of one
 *   status for the whole row.
 * - `overall_status` is deliberately NOT a column here. data-model.md
 *   calls it "a derived rollup for dashboards only — not an independent
 *   source of truth," so it's implemented as a computed accessor on the
 *   model instead (see WeeklyAccomplishmentReport::getOverallStatusAttribute()).
 *   A stored column would risk going stale relative to the four week
 *   statuses it's supposed to summarize; an accessor can't.
 * - week{n}_hours has no BR-3-style derivation requirement in
 *   data-model.md the way DAR's hours_rendered does (WAR has no
 *   time_started/time_ended fields) — it's direct numeric input from the
 *   student, validated in UpdateWarWeekRequest.
 * - cycle1_id / cycle2_id are nullable until each pair is actually
 *   submitted, same nullOnDelete pattern as DAR's cycle_id, for the same
 *   reason: a deleted cycle must never cascade-destroy a report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_accomplishment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->date('month_period');

            foreach ([1, 2, 3, 4] as $week) {
                $table->text("week{$week}_activities")->nullable();
                $table->decimal("week{$week}_hours", 5, 2)->nullable();
                $table->enum("week{$week}_status", ['Draft', 'Pending', 'Approved', 'Returned', 'Late'])
                    ->default('Draft');
                $table->text("week{$week}_comment")->nullable();
            }

            $table->foreignId('cycle1_id')
                ->nullable()
                ->constrained('submission_cycles')
                ->nullOnDelete();
            $table->foreignId('cycle2_id')
                ->nullable()
                ->constrained('submission_cycles')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['student_id', 'month_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_accomplishment_reports');
    }
};
