<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-8: one document per student per month, four independently-reviewed
 * week-sections (BR-7). See the migration for why overall_status is an
 * accessor here rather than a stored column.
 */
class WeeklyAccomplishmentReport extends Model
{
    protected $table = 'weekly_accomplishment_reports';

    protected $fillable = [
        'student_id',
        'month_period',
        'week1_activities', 'week1_hours', 'week1_status', 'week1_comment',
        'week2_activities', 'week2_hours', 'week2_status', 'week2_comment',
        'week3_activities', 'week3_hours', 'week3_status', 'week3_comment',
        'week4_activities', 'week4_hours', 'week4_status', 'week4_comment',
        'cycle1_id',
        'cycle2_id',
    ];

    protected function casts(): array
    {
        return [
            'month_period' => 'date',
            // week{n}_activities are JSON arrays of plain activity-line
            // strings (multi-activity itemization) — one list per week,
            // no per-line times (WAR carries one shared week range).
            'week1_activities' => 'array',
            'week2_activities' => 'array',
            'week3_activities' => 'array',
            'week4_activities' => 'array',
            'week1_hours' => 'decimal:2',
            'week2_hours' => 'decimal:2',
            'week3_hours' => 'decimal:2',
            'week4_hours' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cycle1(): BelongsTo
    {
        return $this->belongsTo(SubmissionCycle::class, 'cycle1_id');
    }

    public function cycle2(): BelongsTo
    {
        return $this->belongsTo(SubmissionCycle::class, 'cycle2_id');
    }

    /**
     * Which pair of weeks ([1, 2] or [3, 4]) a given cycle corresponds to
     * on this document, based on which cycle slot it was submitted into
     * (BR-8: first cycle of the month -> Week 1–2, second -> Week 3–4).
     * Returns null if this cycle isn't linked to this WAR at all.
     *
     * @return array{0:int,1:int}|null
     */
    public function weekPairForCycle(int $cycleId): ?array
    {
        if ($this->cycle1_id === $cycleId) {
            return [1, 2];
        }

        if ($this->cycle2_id === $cycleId) {
            return [3, 4];
        }

        return null;
    }

    /**
     * Display-only date range for a week section, computed from
     * month_period — not a stored field. data-model.md gives WAR no
     * per-week start/end date columns (unlike DAR's report_date), so this
     * exists purely for the PDF's "date-range subheading" per week
     * (pdf-forms.md §3). Convention: Week 1 = days 1–7, Week 2 = 8–14,
     * Week 3 = 15–21, Week 4 = 22–end of month.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public function weekDateRange(int $week): array
    {
        $monthStart = $this->month_period->copy()->startOfMonth();

        $ranges = [
            1 => [1, 7],
            2 => [8, 14],
            3 => [15, 21],
            4 => [22, $monthStart->daysInMonth],
        ];

        [$startDay, $endDay] = $ranges[$week];

        return [
            $monthStart->copy()->addDays($startDay - 1),
            $monthStart->copy()->addDays(min($endDay, $monthStart->daysInMonth) - 1),
        ];
    }

    /**
     * Display-only rollup (data-model.md: "not an independent source of
     * truth" — never branch review logic on this, only on the individual
     * week{n}_status columns, mirroring BR-7).
     */
    public function getOverallStatusAttribute(): string
    {
        $statuses = collect([1, 2, 3, 4])
            ->map(fn ($week) => $this->{"week{$week}_status"});

        if ($statuses->contains('Returned')) {
            return 'Returned';
        }

        if ($statuses->contains('Late')) {
            return 'Late';
        }

        if ($statuses->every(fn ($status) => $status === 'Approved')) {
            return 'Approved';
        }

        if ($statuses->contains('Pending')) {
            return 'Pending';
        }

        return 'Draft';
    }
}
