<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AcademicInfo extends Model
{
    protected $table = 'academic_info';

    protected $fillable = [
       'student_id',
        'major_id',
        'shift_id',
        'batch_year',
        'stage',
        'study_days',
    ];

    // Relationships
        public function student()
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }
    public function major()
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
