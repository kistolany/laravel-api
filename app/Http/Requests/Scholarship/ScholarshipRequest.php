<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScholarshipRequest extends FormRequest
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
                Rule::unique('scholarships', 'student_id')->ignore(
                    $this->route('scholarship')
                ),
            ],
            'nationality'            => 'nullable|string|max:255',
            'ethnicity'              => 'nullable|string|max:255',
            'emergency_name'         => 'required|string|max:255',
            'emergency_relation'     => 'required|string|max:255',
            'emergency_phone'        => 'required|string|max:255',
            'emergency_address'      => 'nullable|string',
            'grade'                  => 'nullable|string|max:10',
            'exam_year'              => 'nullable|integer|min:2000|max:2100',
            'guardians_address'      => 'nullable|string',
            'guardians_phone_number' => 'nullable|string|max:255',
            'guardians_email'        => 'nullable|email|max:255',
        ];
    }
}


