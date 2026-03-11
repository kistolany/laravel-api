<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentGuardian extends Model
{
    protected $table = 'parent_guardians';

    protected $fillable = [
        'student_id',
        'father_name',
        'father_job',
        'mother_name',
        'mother_job',
        'guardian_name',
        'guardian_job',
        'guardian_phone',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'id');
    }
}
