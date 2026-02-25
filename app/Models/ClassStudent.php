<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassStudent extends Pivot
{
    protected $table = 'class_students';

    public $incrementing = true;

    protected $fillable = [
        'class_id',
        'student_id',
        'joined_date',
        'left_date',
        'status',
    ];

    protected $casts = [
        'joined_date' => 'date',
        'left_date' => 'date',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }
}
