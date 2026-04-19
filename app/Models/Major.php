<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Major extends Model
{
    protected $fillable = [
        'faculty_id',
        'name',
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function majorSubjects()
    {
        return $this->hasMany(MajorSubject::class);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', "%{$term}%");
    }
}
