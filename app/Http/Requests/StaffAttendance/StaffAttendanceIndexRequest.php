<?php

namespace App\Http\Requests\StaffAttendance;

use Illuminate\Foundation\Http\FormRequest;

class StaffAttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date',
        ];
    }
}
