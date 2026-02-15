<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class AcademicInfo extends Model
{
    protected $table = 'academic_info';

    protected $fillable = [
       'student_id',
        'major_id',
        'shift_id',
        'batch_year',
        'stage_id',
        'study_days',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // Relationships
    public function major()
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
