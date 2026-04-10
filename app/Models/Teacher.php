<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Authenticatable
{
    use Notifiable;

    protected $table = 'teachers';

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'major_id',
        'subject_id',
        'email',
        'username',
        'password',
        'phone_number',
        'telegram',
        'image',
        'address',
        'role',
        'otp_code',
        'otp_expires_at',
        'is_verified',
        'verified_at',
    ];

    protected $hidden = [
        'password',
        'otp_code',
    ];

    protected function casts(): array
    {
        return [
            'major_id' => 'integer',
            'subject_id' => 'integer',
            'is_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'teacher_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(TeacherRefreshToken::class, 'teacher_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
