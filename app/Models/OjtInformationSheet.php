<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * data-model.md: one row per Student, ever — see the unique constraint
 * on ojt_information_sheets.student_id in the migration for the
 * schema-level enforcement of "one-time only."
 */
class OjtInformationSheet extends Model
{
    protected $fillable = [
        'student_id',
        // A. Personal Data
        'city_address', 'gender', 'contact_number', 'email',
        'birth_date', 'birth_place', 'provincial_address', 'religion',
        'marital_status',
        // B. Family Data
        'father_name', 'father_occupation', 'father_company',
        'father_company_address', 'father_contact',
        'mother_name', 'mother_occupation', 'mother_company',
        'mother_company_address', 'mother_contact',
        'guardian_name', 'guardian_address', 'guardian_contact',
        // C. Scholastic Data
        'tertiary_school', 'tertiary_address', 'tertiary_year_graduated', 'tertiary_honors',
        'secondary_school', 'secondary_address', 'secondary_year_graduated', 'secondary_honors',
        'primary_school', 'primary_address', 'primary_year_graduated', 'primary_honors',
        // D. Health Data
        'height', 'weight', 'blood_type', 'health_problem',
        'vaccination_status', 'vaccine_type', 'vaccination_place', 'vaccination_date',
        'health_insurance_type', 'health_insurance_specify',
        // Attestation
        'signed_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'vaccination_date' => 'date',
            'signed_date' => 'date',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // Section E — repeatable, may legitimately be empty (form scopes
    // this section to 4th-year students only).
    public function workExperiences(): HasMany
    {
        return $this->hasMany(OjtWorkExperience::class);
    }
}
