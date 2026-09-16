<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionCycle extends Model
{
    protected $fillable = [
        'coordinator_id',
        'cycle_name',
        'coverage_start_date',
        'coverage_end_date',
        'deadline_date',
    ];

    protected function casts(): array
    {
        return [
            'coverage_start_date' => 'date',
            'coverage_end_date' => 'date',
            'deadline_date' => 'date',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Coordinator::class);
    }

    // DARs submitted through this cycle. Only reports with a non-null
    // cycle_id belong here — Drafts (cycle_id still null) won't appear.
    public function dailyAccomplishmentReports(): HasMany
    {
        return $this->hasMany(DailyAccomplishmentReport::class, 'cycle_id');
    }

    // BR-6: lateness is determined ONLY by comparing a submission's
    // created_at against this cycle's deadline_date — never against the
    // activity/report date being logged. This helper centralizes that
    // check so DAR/WAR/MAR code (once it exists) doesn't reimplement the
    // comparison inconsistently.
    public function isPastDeadline(?\DateTimeInterface $when = null): bool
    {
        $when ??= now();

        return $when->greaterThan($this->deadline_date->endOfDay());
    }
}
