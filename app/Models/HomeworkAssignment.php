<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkAssignment extends Model
{
    protected $table = 'homework_assignments';

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'title',
        'description',
        'attachment_url',
        'attachment_name',
        'due_date',
        'max_score',
        'is_active',
    ];

    protected $casts = [
        'due_date'  => 'datetime',
        'max_score' => 'integer',
        'is_active' => 'boolean',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'homework_id');
    }

    /**
     * Check if the assignment is past its due date.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date->isPast();
    }
}
