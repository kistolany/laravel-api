<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Shift;

class ScheduleMakeupSession extends Model
{
    protected $fillable = [
        'schedule_id',
        'makeup_schedule_id',
        'teacher_id',
        'makeup_date',
        'makeup_session',
        'shift_id',
        'attendance_status',
        'absent_week_number',
        'absent_date',
        'status',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'makeup_date'        => 'date',
        'absent_date'        => 'date',
        'makeup_session'     => 'integer',
        'absent_week_number' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function makeupSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'makeup_schedule_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function makeupShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
