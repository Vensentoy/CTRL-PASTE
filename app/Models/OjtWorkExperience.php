<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtWorkExperience extends Model
{
    protected $fillable = [
        'ojt_information_sheet_id',
        'ojt_assignment',
        'position',
        'inclusive_start_date',
        'inclusive_end_date',
        'ojt_site_address',
    ];

    protected function casts(): array
    {
        return [
            'inclusive_start_date' => 'date',
            'inclusive_end_date' => 'date',
        ];
    }

    public function informationSheet(): BelongsTo
    {
        return $this->belongsTo(OjtInformationSheet::class);
    }
}
