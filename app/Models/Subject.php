<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{

    protected $fillable = [
        'subject_Code',
        'name_kh',
        'name_eg'
    ];

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name_kh', 'like', "%{$term}%")
                ->orWhere('name_eg', 'like', "%{$term}%")
                ->orWhere('subject_Code', 'like', "%{$term}%");
        });
    }
}
