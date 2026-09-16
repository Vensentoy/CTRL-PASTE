<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * data-model.md: append-only. Nothing in this app should ever update()
 * or delete() a row here directly — write access is meant to go
 * exclusively through App\Services\AuditLogger::log().
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action_type',
        'action_details',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
