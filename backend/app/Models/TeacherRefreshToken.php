<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherRefreshToken extends Model
{
    protected $table = 'teacher_refresh_tokens';

    protected $fillable = [
        'teacher_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
