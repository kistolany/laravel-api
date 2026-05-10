<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSchedule extends Model
{
    protected $table = 'class_schedules';

    protected $fillable = [
        'class_id',
        'class_program_id',
        'subject_id',
        'teacher_id',
        'shift_id',
        'room_id',
        'day_of_week',
        'academic_year',
        'year_level',
        'semester',
        'total_male',
        'total_female',
        'code',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'year_level' => 'integer',
        'semester' => 'integer',
        'total_male' => 'integer',
        'total_female' => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function classProgram(): BelongsTo
    {
        return $this->belongsTo(ClassProgram::class, 'class_program_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function roomModel(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
