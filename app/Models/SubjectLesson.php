<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectLesson extends Model
{
    protected $table = 'subject_lessons';

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'title',
        'description',
        'file_url',
        'file_name',
        'file_type',
        'file_size',
        'lesson_date',
    ];

    protected $casts = [
        'file_size'   => 'integer',
        'lesson_date' => 'date',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
