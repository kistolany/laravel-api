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
        'student_type',
        'tuition_plan',
        'tuition_plan_assigned_at',
        'exam_place',
        'bacll_code',
        'grade',
        'doc',
        'registration_date',
        'short_docs_status',
        'status',
        'other_notes'
    ];

    protected $casts = [
        'short_docs_status' => 'boolean',
        'dob' => 'date',
        'tuition_plan_assigned_at' => 'datetime',
    ];

    public function academicInfo(): HasOne
    {
        return $this->hasOne(AcademicInfo::class, 'student_id', 'id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'student_id', 'id');
    }

    public function parentGuardian(): HasOne
    {
        return $this->hasOne(ParentGuardian::class, 'student_id', 'id');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(StudentRegistration::class, 'student_id', 'id');
    }

    public function scholarship(): HasOne
    {
        return $this->hasOne(Scholarship::class, 'student_id', 'id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_students', 'student_id', 'class_id')
            ->using(ClassStudent::class)
            ->withPivot(['joined_date', 'left_date', 'status'])
            ->withTimestamps();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(StudentScore::class, 'student_id', 'id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentPayment::class, 'student_id', 'id');
    }

    public function getBarcodeAttribute(): string
    {
        return 'B' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
