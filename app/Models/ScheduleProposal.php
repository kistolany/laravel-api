<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleProposal extends Model
{
    protected $table = 'schedule_proposals';

    protected $fillable = [
        'class_id', 'subject_id', 'shift_id', 'day_of_week',
        'room', 'academic_year', 'year_level', 'semester',
        'teacher_id', 'sent_by',
        'status', 'reject_reason', 'responded_at', 'schedule_id',
    ];

    protected $casts = [
        'year_level'   => 'integer',
        'semester'     => 'integer',
        'responded_at' => 'datetime',
    ];

    public function classroom(): BelongsTo  { return $this->belongsTo(Classes::class,       'class_id');   }
    public function subject(): BelongsTo    { return $this->belongsTo(Subject::class,        'subject_id'); }
    public function shift(): BelongsTo      { return $this->belongsTo(Shift::class,          'shift_id');   }
    public function teacher(): BelongsTo    { return $this->belongsTo(Teacher::class,        'teacher_id'); }
    public function sentBy(): BelongsTo     { return $this->belongsTo(User::class,           'sent_by');    }
    public function schedule(): BelongsTo   { return $this->belongsTo(ClassSchedule::class,  'schedule_id');}
}
