<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'teachers';

    protected $fillable = [
        'teacher_id',
        'first_name',
        'last_name',
        'gender',
        'dob',
        'nationality',
        'religion',
        'marital_status',
        'national_id',
        'major_id',
        'subject_id',
        'email',
        'username',
        'password',
        'phone_number',
        'telegram',
        'image',
        'cv_file',
        'id_card_file',
        'lesson_files',
        'address',
        'position',
        'degree',
        'specialization',
        'contract_type',
        'salary_type',
        'salary',
        'experience',
        'join_date',
        'emergency_name',
        'emergency_phone',
        'note',
        'role',
        'status',
        'deleted_by',
        'delete_reason',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'major_id'      => 'integer',
            'subject_id'    => 'integer',
            'experience'    => 'integer',
            'salary'        => 'decimal:2',
            'dob'           => 'date',
            'join_date'     => 'date',
            'deleted_at'    => 'datetime',
            'lesson_files'  => 'array',
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

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
