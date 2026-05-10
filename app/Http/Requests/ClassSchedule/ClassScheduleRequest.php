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
            'class_id'   => 'required|exists:classes,id',
            'class_program_id' => 'nullable|exists:class_programs,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'shift_id'   => 'required|exists:shifts,id',
            'day_of_week'  => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'academic_year' => 'nullable|string|max:20',
            'year_level'    => 'nullable|integer|min:1|max:6',
            'semester'      => 'nullable|integer|min:1|max:2',
            'total_male'   => 'nullable|integer|min:0',
            'total_female' => 'nullable|integer|min:0',
            'room_id'      => 'nullable|exists:rooms,id',
            'code'       => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
