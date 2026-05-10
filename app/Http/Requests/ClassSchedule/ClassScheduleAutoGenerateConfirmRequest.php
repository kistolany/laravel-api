<?php

namespace App\Http\Requests\ClassSchedule;

use Illuminate\Foundation\Http\FormRequest;

class ClassScheduleAutoGenerateConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedules' => 'required|array|min:1',
            'schedules.*.class_program_id' => 'nullable|integer|exists:class_programs,id',
            'schedules.*.class_id' => 'required|integer|exists:classes,id',
            'schedules.*.subject_id' => 'required|integer|exists:subjects,id',
            'schedules.*.teacher_id' => 'required|integer|exists:teachers,id',
            'schedules.*.shift_id' => 'required|integer|exists:shifts,id',
            'schedules.*.day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.room' => 'nullable|string|max:100',
            'schedules.*.academic_year' => 'required|string|max:20',
            'schedules.*.year_level' => 'required|integer|min:1|max:6',
            'schedules.*.semester' => 'required|integer|min:1|max:3',
        ];
    }
}
