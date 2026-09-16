<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coordinator extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'department_area',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // All students CURRENTLY assigned to this coordinator (BR-1, BR-11).
    // Any query for "my students" anywhere in the app should go through
    // this relationship, never a raw unscoped Student::all().
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    // Submission Cycles this coordinator has manually created (BR-5).
    public function submissionCycles(): HasMany
    {
        return $this->hasMany(SubmissionCycle::class);
    }
}
