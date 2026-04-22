<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'leave_requests';

    protected $fillable = [
        'requester_type',
        'requester_id',
        'requester_name',
        'requester_name_kh',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'requester_id')->where('requester_type', 'student');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'requester_id')->where('requester_type', 'teacher');
    }
}
