<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// 1. YOU MUST ADD THIS LINE:
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorSubject extends Model
{
    protected $table = 'major_subjects';

    protected $fillable = [
        'major_id',
        'subject_id',
        'year_level',
        'semester',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function major(): BelongsTo
    {
        // This links major_id to the Majors table
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function subject(): BelongsTo
    {
        // This links subject_id to the Subjects table
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}