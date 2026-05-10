<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassProgram extends Model
{
    protected $table = 'class_programs';

    protected $fillable = ['class_id', 'major_id', 'shift_id', 'year_level', 'semester', 'academic_year', 'section', 'max_students'];

    protected $casts = [
        'year_level' => 'integer',
        'semester' => 'integer',
        'max_students' => 'integer',
    ];

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
