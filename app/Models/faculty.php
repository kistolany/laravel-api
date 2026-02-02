<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class faculty extends Model
{
    use HasFactory;
    protected $fillable = ['name_kh', 'name_eg'];


    public function scopeSearch($query, $term)
{
    return $query->where(function ($q) use ($term) {
        $q->where('name_kh', 'like', "%{$term}%")
          ->orWhere('name_eg', 'like', "%{$term}%");
    });
}
}
