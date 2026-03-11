<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classes extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'code',
        'major_id',
        'shift_id',
        'academic_year',
        'year_level',
        'semester',
        'section',
        'max_students',
        'is_active',
    ];

    protected $casts = [
        'year_level' => 'integer',
        'semester' => 'integer',
        'max_students' => 'integer',
        'is_active' => 'boolean',
    ];

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id', 'id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Students::class, 'class_students', 'class_id', 'student_id')
            ->using(ClassStudent::class)
            ->withPivot(['joined_date', 'left_date', 'status'])
            ->withTimestamps();
    }
}
