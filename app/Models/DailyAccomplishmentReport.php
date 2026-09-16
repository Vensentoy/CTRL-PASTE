<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class DailyAccomplishmentReport extends Model
{
    use SoftDeletes;

    protected $table = 'daily_accomplishment_reports';

    protected $fillable = [
        'student_id',
        'cycle_id',
        'report_date',
        'activities_text',
        'time_started',
        'time_ended',
        'remarks_student',
        'status',
        'coordinator_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    // hours_rendered is intentionally NOT fillable (BR-3). It must only
    // ever be set via calculateHoursRendered() below, called server-side
    // whenever time_started/time_ended are written.

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'reviewed_at' => 'datetime',
            'hours_rendered' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SubmissionCycle::class, 'cycle_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * BR-3: Hours Rendered = Time Ended − Time Started, always computed
     * server-side. Call this and assign the result to hours_rendered
     * yourself in the controller/service — it is deliberately NOT wired
     * into a model event/mutator here, so that a save without a real
     * time change (e.g. a status-only update from a review action) can't
     * silently zero out or recompute a value it didn't intend to touch.
     */
    public function calculateHoursRendered(): float
    {
        $start = Carbon::parse($this->time_started);
        $end = Carbon::parse($this->time_ended);

        // Explicit `true` here is required, not stylistic: Carbon 3
        // (this project uses 3.13.2, confirmed via composer.lock) changed
        // diffInMinutes()'s default $absolute from true (Carbon 2's
        // behavior) to false, so this could silently return a negative
        // value depending on argument order without this flag.
        return round($end->diffInMinutes($start, true) / 60, 2);
    }

    /**
     * BR-4 date-bound check against the owning student's OJT window.
     * Does NOT check "not in the future" — that's a plain now()-based
     * check the caller can do inline; this method exists specifically for
     * the student-relative bounds that require loading the Student row.
     */
    public function isWithinStudentOjtWindow(): bool
    {
        $this->loadMissing('student');

        return $this->report_date->betweenIncluded(
            $this->student->ojt_start_date,
            $this->student->ojt_completion_date
        );
    }
}