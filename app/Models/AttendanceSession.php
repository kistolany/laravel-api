<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $table = 'attendance_sessions';

    protected $fillable = [
        'class_id',
        'subject_id',
        'major_id',
        'shift_id',
        'academic_year',
        'year_level',
        'semester',
        'session_date',
        'session_number',
        'teacher_id',
        'actual_teacher_id',
    ];

    protected $casts = [
        'session_date' => 'date',
        'year_level' => 'integer',
        'semester' => 'integer',
        'session_number' => 'integer',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    // Who actually taught this session — null means the original scheduled teacher
    public function actualTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'actual_teacher_id');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'attendance_session_id');
    }
}
