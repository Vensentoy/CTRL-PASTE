<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * data-model.md / pdf-forms.md — Section E "OJT Work Experiences (4th
 * Year Students)": a repeatable list on the Info Sheet, so it needs its
 * own table rather than fixed columns on ojt_information_sheets. Not
 * every student will have rows here (the form itself scopes this
 * section to 4th-year students) — an empty list is valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ojt_information_sheet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('ojt_assignment');
            $table->string('position');
            $table->date('inclusive_start_date');
            $table->date('inclusive_end_date');
            $table->string('ojt_site_address');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_work_experiences');
    }
};
