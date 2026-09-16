<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SubmissionCycle — a coordinator-defined period for one of the
 * twice-monthly meetings (data-model.md).
 *
 * - There is no fixed/automatic system calendar (BR-5) — every row here is
 *   manually created by a Coordinator. No seeder or scheduled job should
 *   ever auto-generate these.
 * - `deadline_date` is the ONLY thing that determines whether a later
 *   DAR/WAR/MAR submission is "Late" (BR-6). Lateness is never derived
 *   from `coverage_start_date`/`coverage_end_date` or from the activity
 *   date being reported on — those two are display/scoping fields only.
 * - restrictOnDelete on coordinator_id: a coordinator cannot be deleted
 *   while they still own submission cycles, mirroring the same
 *   restrictOnDelete pattern used for coordinator_id on `students`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinator_id')
                ->constrained('coordinators')
                ->restrictOnDelete();

            $table->string('cycle_name');
            $table->date('coverage_start_date');
            $table->date('coverage_end_date');
            $table->date('deadline_date');

            $table->timestamps();

            $table->index(['coordinator_id', 'deadline_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_cycles');
    }
};
