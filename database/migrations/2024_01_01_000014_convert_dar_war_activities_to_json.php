<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DAR/WAR multi-activity itemization
 * (OPENCODE_MULTI_ACTIVITY_HANDOFF.md).
 *
 * Real filled-out forms (lester-reference-examples/ — Lester S. Tapao's
 * actual submitted DAR/WAR, not the blank official templates) show
 * MULTIPLE itemized activities per DAR date (each with its own
 * time-in/time-out and duration, one shared Remarks/Status) and MULTIPLE
 * activity lines per WAR week (plain text, one shared Date Started / Date
 * Ended / No. of Hours / Remarks-Status for the whole week). The old
 * schema stored only one activity blob per date/week.
 *
 * JSON columns, NOT a child relational table — deliberate tradeoff (see
 * handoff + PROJECT_STATE.md): same itemized data, identical correct PDF
 * output, far fewer files touched (migration + model + request + form +
 * PDF + tests stay in sync in one session).
 *
 * - daily_accomplishment_reports: drop activities_text / time_started /
 *   time_ended, add `activities` JSON =
 *   [{activity, time_started, time_ended}, ...] (min 1, max 20 items —
 *   enforced in StoreDarRequest/UpdateDarRequest, not here).
 *   `hours_rendered` stays a stored, computed column (BR-3) — now the
 *   SUM of the entries' durations, recomputed by the model's
 *   setActivitiesAttribute() mutator whenever `activities` is written.
 * - weekly_accomplishment_reports: week{1..4}_activities TEXT -> JSON =
 *   ["line one", "line two", ...] (array of plain strings, no per-line
 *   times). Column names kept so the week{n}_* status/hours/comment
 *   grouping (BR-7/BR-8) is untouched. week{n}_hours stay independently
 *   entered as today — never derived from DAR rows.
 * - MAR untouched (already matches its real example).
 *
 * No production data exists (seed/demo only, next year's cohort), so no
 * data migration of old rows — reset/reseed freely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_accomplishment_reports', function (Blueprint $table) {
            $table->dropColumn(['activities_text', 'time_started', 'time_ended']);
        });

        Schema::table('daily_accomplishment_reports', function (Blueprint $table) {
            // NOT NULL by construction: every write path (store/update,
            // seeder) goes through validation requiring min 1 entry, and
            // the model mutator always sets this alongside hours_rendered.
            $table->json('activities');
        });

        Schema::table('weekly_accomplishment_reports', function (Blueprint $table) {
            foreach ([1, 2, 3, 4] as $week) {
                $table->dropColumn("week{$week}_activities");
            }
        });

        Schema::table('weekly_accomplishment_reports', function (Blueprint $table) {
            foreach ([1, 2, 3, 4] as $week) {
                // Nullable like before: weeks fill in progressively across
                // the month (BR-8), so untouched weeks stay null.
                $table->json("week{$week}_activities")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_accomplishment_reports', function (Blueprint $table) {
            $table->dropColumn('activities');
        });

        Schema::table('daily_accomplishment_reports', function (Blueprint $table) {
            $table->text('activities_text');
            $table->time('time_started')->nullable();
            $table->time('time_ended')->nullable();
        });

        Schema::table('weekly_accomplishment_reports', function (Blueprint $table) {
            foreach ([1, 2, 3, 4] as $week) {
                $table->dropColumn("week{$week}_activities");
            }
        });

        Schema::table('weekly_accomplishment_reports', function (Blueprint $table) {
            foreach ([1, 2, 3, 4] as $week) {
                $table->text("week{$week}_activities")->nullable();
            }
        });
    }
};
