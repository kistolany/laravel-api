<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Major extends Model
{
      protected $fillable = [
        'faculty_id',
        'name_kh',
        'name_eg'
    ];

    // Prerae for search
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name_kh', 'like', "%{$term}%")
                ->orWhere('name_eg', 'like', "%{$term}%")
                ->orWhere('faculty_id', 'like', "%{$term}%");
        });
    }
}
