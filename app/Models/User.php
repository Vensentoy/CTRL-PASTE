<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'role',
        'username',
        'password',
        'must_change_password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // Login uses `username`, not `email` — no email verification flow
    // exists in this system (data-model.md).
    public function username(): string
    {
        return 'username';
    }

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function coordinator(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Coordinator::class);
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }
}
