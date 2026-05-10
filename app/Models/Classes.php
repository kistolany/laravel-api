<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classes extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'is_active',
    ];

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id', 'id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(ClassProgram::class, 'class_id', 'id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'class_id', 'id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_id', 'id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Students::class, 'class_students', 'class_id', 'student_id')
            ->using(ClassStudent::class)
            ->withPivot(['joined_date', 'left_date', 'status'])
            ->withTimestamps();
    }
}
