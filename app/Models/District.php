<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class District extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name_kh',
        'name_en',
       
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function province()
    {
        return $this->belongsTo(\App\Models\Province::class);
    }

    public function communes()
    {
        return $this->hasMany(\App\Models\Commune::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Search Scope
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name_kh', 'like', "%{$term}%")
              ->orWhere('name_en', 'like', "%{$term}%");
        });
    }
}
