<?php

namespace App\Http\Requests\StaffAttendance;

use Illuminate\Foundation\Http\FormRequest;

class StaffAttendanceRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->query('from', now()->startOfMonth()->toDateString()),
            'to' => $this->query('to', now()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ];
    }
}
