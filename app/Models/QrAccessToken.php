<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class QrAccessToken extends Model
{
    protected $fillable = [
        'token_hash',
        'expires_at',
        'max_uses',
        'used_count',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    public function isExhausted(): bool
    {
        return $this->used_count >= $this->max_uses;
    }

    public function markUsed(): void
    {
        $this->increment('used_count');
    }
}
