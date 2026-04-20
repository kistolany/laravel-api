<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScore extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'academic_year',
        'year_level',
        'semester',
        'class_score',
        'assignment_score',
        'midterm_score',
        'final_score',
    ];

    protected $casts = [
        'class_score' => 'float',
        'assignment_score' => 'float',
        'midterm_score' => 'float',
        'final_score' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id', 'id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id');
    }

    public function getTotalAttribute(): float
    {
        return round(
            (float) $this->class_score
            + (float) $this->assignment_score
            + (float) $this->midterm_score
            + (float) $this->final_score,
            2
        );
    }
}
