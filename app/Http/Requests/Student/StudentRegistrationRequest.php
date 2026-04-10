<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                Rule::unique('student_registrations', 'student_id')->ignore(
                    $this->route('student_registration')
                ),
            ],
            'high_school_name'     => 'nullable|string|max:255',
            'high_school_province' => 'nullable|string|max:255',
            'bacii_exam_year'      => 'nullable|integer|min:2000|max:2100',
            'bacii_grade'          => 'nullable|string|max:10',
            'target_degree'        => 'nullable|string|max:255',
            'diploma_attached'     => 'nullable|boolean',
        ];
    }
}


