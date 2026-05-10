<?php

namespace App\Http\Requests\ClassSchedule;

use Illuminate\Foundation\Http\FormRequest;

class ClassScheduleAutoGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots' => 'required|array|min:1',
            'slots.*.class_program_id' => 'nullable|integer|exists:class_programs,id',
            'slots.*.class_id' => 'required|integer|exists:classes,id',
            'slots.*.subject_id' => 'required|integer|exists:subjects,id',
            'slots.*.shift_id' => 'required|integer|exists:shifts,id',
            'slots.*.day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'slots.*.room' => 'nullable|string|max:100',
            'slots.*.academic_year' => 'required|string|max:20',
            'slots.*.year_level' => 'required|integer|min:1|max:6',
            'slots.*.semester' => 'required|integer|min:1|max:3',
        ];
    }
}
