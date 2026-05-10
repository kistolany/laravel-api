<?php

namespace App\Http\Requests\ClassSchedule;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'required|integer|exists:classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'shift_id' => 'required|integer|exists:shifts,id',
            'day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'room' => 'nullable|string|max:100',
            'academic_year' => 'nullable|string|max:20',
            'year_level' => 'nullable|integer|min:1|max:6',
            'semester' => 'nullable|integer|min:1|max:3',
            'teacher_id' => 'required|integer|exists:teachers,id',
        ];
    }
}
