<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * data-model.md: one row per student per month, submitted once the
 * month's coverage is complete. Single status field (unlike WAR's four
 * week-sections) — the review trail shape (status, cycle_id,
 * coordinator_comment, reviewed_by, reviewed_at) matches DAR's, not
 * WAR's per-week columns.
 */
class MonthlyAccomplishmentReport extends Model
{
    protected $table = 'monthly_accomplishment_reports';

    protected $fillable = [
        'student_id',
        'cycle_id',
        'month_period',
        'activities_text',
        'monthly_total_hours',
        'remarks',
        'status',
        'coordinator_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'month_period' => 'date',
            'monthly_total_hours' => 'decimal:2',
            'reviewed_at' => 'datetime',
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
}
