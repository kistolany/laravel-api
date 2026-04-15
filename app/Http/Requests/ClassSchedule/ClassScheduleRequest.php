<?php

namespace App\Http\Requests\ClassSchedule;

use Illuminate\Foundation\Http\FormRequest;

class ClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id'      => 'required|exists:classes,id',
            'subject_id'    => 'required|exists:subjects,id',
            'teacher_id'    => 'required|exists:teachers,id',
            'shift_id'      => 'required|exists:shifts,id',
            'day_of_week'   => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'academic_year' => 'required|string|max:20',
            'year_level'    => 'required|integer|min:1|max:6',
            'semester'      => 'required|integer|min:1|max:3',
            'room'          => 'nullable|string|max:100',
        ];
    }
}
