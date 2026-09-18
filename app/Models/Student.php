<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'coordinator_id',
        'student_id_number',
        'surname',
        'given_name',
        'middle_name',
        'course',
        'major',
        'year_section',
        'ojt_start_date',
        'ojt_completion_date',
        'required_hours',
        'ojt_status',
    ];

    // completed_hours is intentionally NOT fillable. It is derived from
    // approved DAR + WAR-week hours only (BR-2/BR-10: MAR rows are
    // excluded since their total is derived from WAR weeks — counting
    // both would double-count) and must only ever be set by a
    // server-side recalculation routine (data-model.md, BR-3 principle).
    // Do not add it to $fillable when DAR/WAR/MAR land later.

    protected function casts(): array
    {
        return [
            'ojt_start_date' => 'date',
            'ojt_completion_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Coordinator::class);
    }

    // Full company history (BR-12) — includes closed and active rows.
    public function companyAssignments(): HasMany
    {
        return $this->hasMany(CompanyAssignment::class);
    }

    // The one row with end_date null, if any. Null itself is valid — a
    // student can legitimately have no active company assignment yet.
    public function activeCompanyAssignment(): ?CompanyAssignment
    {
        return $this->companyAssignments()->whereNull('end_date')->first();
    }

    // Includes soft-deleted rows filtered out by default (BR-9) — use
    // ->withTrashed() explicitly wherever a review/audit view needs them.
    public function dailyAccomplishmentReports(): HasMany
    {
        return $this->hasMany(DailyAccomplishmentReport::class);
    }

    // One row per calendar month, each with four independently-reviewed
    // week-sections (BR-8) — see WeeklyAccomplishmentReport for why
    // overall_status is computed rather than stored.
    public function weeklyAccomplishmentReports(): HasMany
    {
        return $this->hasMany(WeeklyAccomplishmentReport::class);
    }

    // One row per calendar month, single status per row (unlike WAR) —
    // see MonthlyAccomplishmentReport for why it mirrors DAR's review
    // shape rather than WAR's per-week one.
    public function monthlyAccomplishmentReports(): HasMany
    {
        return $this->hasMany(MonthlyAccomplishmentReport::class);
    }

    // data-model.md: "One Student → exactly one OJTInformationSheet
    // (ever)" — enforced at the schema level by a unique constraint on
    // ojt_information_sheets.student_id, not just by this relation.
    public function informationSheet(): HasOne
    {
        return $this->hasOne(OjtInformationSheet::class);
    }

    public function fullName(): string
    {
        return trim("{$this->given_name} {$this->middle_name} {$this->surname}");
    }
}
