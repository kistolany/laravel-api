<?php

namespace App\Http\Requests\TeacherAttendance;

use Illuminate\Foundation\Http\FormRequest;

class TeacherAttendanceBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.teacher_id' => 'required|exists:teachers,id',
            'records.*.session' => 'required|in:1,2',
            'records.*.status' => 'required|in:Present,Absent',
            'records.*.check_in_time' => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.note' => 'nullable|string|max:255',
            'records.*.replace_teacher_id' => 'nullable|exists:teachers,id',
            'records.*.replace_status'     => 'nullable|in:Present,Absent',
            'records.*.replace_subject_id' => 'nullable|exists:subjects,id',
        ];
    }
}
