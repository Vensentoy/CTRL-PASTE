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
        'activities',
        'remarks_student',
        'status',
        'coordinator_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    // hours_rendered is intentionally NOT fillable (BR-3). It must only
    // ever be set via setActivitiesAttribute() below, which recomputes it
    // server-side from the `activities` array on every write of that
    // field — never accepted as input, never trusted from the client.

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'reviewed_at' => 'datetime',
            'hours_rendered' => 'decimal:2',
            'activities' => 'array',
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
     * BR-3: Hours Rendered = Σ over each activity entry of
     * (time_ended − time_started), always computed server-side.
     *
     * Wired as a mutator on `activities` (not a saving-event observer):
     * hours recompute exactly when the itemized entries are written, and
     * never on a save that doesn't touch them (status-only updates from
     * review actions, submits, soft deletes), so those paths can't
     * silently zero out or recompute a value they didn't intend to touch.
     * Hydration from the DB bypasses mutators, so stored totals are read
     * back untouched.
     *
     * @param  array|string  $value  The entries array (or its JSON form).
     */
    public function setActivitiesAttribute(mixed $value): void
    {
        $entries = is_string($value)
            ? (json_decode($value, true) ?? [])
            : array_values((array) $value);

        $this->attributes['activities'] = json_encode(array_values($entries));
        $this->attributes['hours_rendered'] = self::hoursForActivities($entries);
    }

    /**
     * Total hours for a set of activity entries. Pure helper — the same
     * math the mutator above applies, exposed so seeders/tests can state
     * expectations without duplicating the formula.
     */
    public static function hoursForActivities(array $entries): float
    {
        $minutes = 0;

        foreach ($entries as $entry) {
            $minutes += self::minutesBetween(
                $entry['time_started'] ?? null,
                $entry['time_ended'] ?? null
            );
        }

        return round($minutes / 60, 2);
    }

    /**
     * One-line summary for list rows (index/review screens): the first
     * activity's text plus a "+N more" suffix when the date has several
     * entries. Full detail lives on the edit form and the PDF.
     */
    public function activitiesSummary(): string
    {
        $entries = $this->activities ?? [];

        if (empty($entries)) {
            return '—';
        }

        $first = $entries[0]['activity'] ?? '—';
        $extra = count($entries) - 1;

        return $extra > 0 ? "{$first} (+{$extra} more)" : (string) $first;
    }

    /**
     * Per-entry durations (hours), parallel to the `activities` array —
     * feeds the PDF template's per-row "No. of Hours" cells.
     *
     * @return float[]
     */
    public function activityEntryHours(): array
    {
        return array_map(
            fn ($entry) => round(self::minutesBetween(
                $entry['time_started'] ?? null,
                $entry['time_ended'] ?? null
            ) / 60, 2),
            $this->activities ?? []
        );
    }

    /**
     * Duration of one entry in whole minutes. Malformed entries (missing
     * times) contribute 0 rather than throwing — validation guarantees
     * shape on write, this only guards reads of legacy/hand-made rows.
     */
    protected static function minutesBetween(mixed $start, mixed $end): int
    {
        if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
            return 0;
        }

        // Explicit `true` here is required, not stylistic: Carbon 3
        // (this project uses 3.13.2, confirmed via composer.lock) changed
        // diffInMinutes()'s default $absolute from true (Carbon 2's
        // behavior) to false, so this could silently return a negative
        // value depending on argument order without this flag.
        return Carbon::parse($end)->diffInMinutes(Carbon::parse($start), true);
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