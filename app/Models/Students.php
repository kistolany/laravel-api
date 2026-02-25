<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Students extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'full_name_kh',
        'full_name_en',
        'gender',
        'dob',
        'phone',
        'email',
        'image',
        'id_card_number',
        'registration_date',
        'short_docs_status',
        'status',
        'other_notes'
    ];

    protected $casts = [
        'short_docs_status' => 'boolean',
        'dob' => 'date',
    ];

    public function academicInfo(): HasOne
    {
        return $this->hasOne(AcademicInfo::class, 'student_id', 'id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'student_id', 'id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_students', 'student_id', 'class_id')
            ->using(ClassStudent::class)
            ->withPivot(['joined_date', 'left_date', 'status'])
            ->withTimestamps();
    }

    public function getBarcodeAttribute(): string
    {
        return 'B' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
