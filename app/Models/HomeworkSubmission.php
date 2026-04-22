<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    protected $table = 'homework_submissions';

    protected $fillable = [
        'homework_id',
        'student_id',
        'file_url',
        'file_name',
        'file_type',
        'file_size',
        'note',
        'submitted_at',
        'is_late',
        'score',
        'feedback',
    ];

    protected $casts = [
        'file_size'    => 'integer',
        'submitted_at' => 'datetime',
        'is_late'      => 'boolean',
        'score'        => 'decimal:2',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class, 'homework_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }
}
