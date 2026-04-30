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
            'records.*.status' => 'required|in:Present,Absent,Late,Leave',
            'records.*.check_in_time' => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.note' => 'nullable|string|max:255',
        ];
    }
}
