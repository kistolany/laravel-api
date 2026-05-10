<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Subject;

class TeacherAttendance extends Model
{
    protected $fillable = [
        'teacher_id',
        'schedule_id',
        'attendance_date',
        'session',
        'status',
        'check_in_time',
        'check_out_time',
        'note',
        'recorded_by',
        'replace_teacher_id',
        'replace_status',
        'replace_subject_id',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function replaceTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'replace_teacher_id');
    }

    public function replaceSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'replace_subject_id');
    }
}
