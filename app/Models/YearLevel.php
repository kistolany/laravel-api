<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class YearLevel extends Model
{
    protected $fillable = [
        'name',
        'code',
        'number',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'number' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('number')
            ->orderBy('name');
    }
}
