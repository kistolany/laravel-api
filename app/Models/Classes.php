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
    ];

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
