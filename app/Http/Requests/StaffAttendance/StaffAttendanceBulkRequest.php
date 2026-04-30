<?php

namespace App\Http\Requests\StaffAttendance;

use Illuminate\Foundation\Http\FormRequest;

class StaffAttendanceBulkRequest extends FormRequest
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
            'records.*.user_id' => 'required|exists:users,id',
            'records.*.status' => 'required|in:Present,Absent,Late,Leave',
            'records.*.check_in_time' => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.note' => 'nullable|string|max:255',
        ];
    }
}
