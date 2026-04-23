<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_type' => 'required|in:PAY,PENDING,PASS,FAIL',
            'tuition_plan' => [
                Rule::requiredIf(fn () => $this->input('student_type') === 'PASS'),
                'nullable',
                'string',
                Rule::in(['SCHOLARSHIP_FULL', 'SCHOLARSHIP_70', 'SCHOLARSHIP_50', 'SCHOLARSHIP_30']),
            ],
        ];
    }
}


