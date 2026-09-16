<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAssignment extends Model
{
    protected $fillable = [
        'student_id',
        'company_name',
        'department_area',
        'job_designation',
        'mobile_number',
        'start_date',
        'end_date',
    ];

    // BR-12: never update company_name (or any identity field) on an
    // existing row to represent a company change. To "switch companies":
    //   1. close the current active row — set its end_date
    //   2. create a brand-new row for the new company
    // Do this inside a transaction; see migration note re: no schema-level
    // guarantee of a single active row per student.

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isActive(): bool
    {
        return is_null($this->end_date);
    }
}
