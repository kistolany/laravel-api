<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class TeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'sync' => [
                'teacher_id' => 'required|integer|exists:teachers,id',
                'slots' => 'required|array',
                'slots.*.subject_id' => 'required|integer|exists:subjects,id',
                'slots.*.shift_id' => 'required|integer|exists:shifts,id',
                'slots.*.day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            ],
            default => [],
        };
    }
}
