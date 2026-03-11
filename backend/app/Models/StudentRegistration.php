<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistration extends Model
{
    protected $table = 'student_registrations';

    protected $fillable = [
        'student_id',
        'high_school_name',
        'high_school_province',
        'bacii_exam_year',
        'bacii_grade',
        'target_degree',
        'diploma_attached',
    ];

    protected $casts = [
        'diploma_attached' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'id');
    }
}
