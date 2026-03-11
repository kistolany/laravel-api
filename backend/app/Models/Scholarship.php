<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scholarship extends Model
{
    protected $table = 'scholarships';

    protected $fillable = [
        'student_id',
        'nationality',
        'ethnicity',
        'emergency_name',
        'emergency_relation',
        'emergency_phone',
        'emergency_address',
        'grade',
        'exam_year',
        'guardians_address',
        'guardians_phone_number',
        'guardians_email',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'id');
    }
}
