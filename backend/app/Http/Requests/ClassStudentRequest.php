<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required',
            'joined_date' => 'required|date',
            'left_date' => 'nullable|date',
            'status' => 'nullable|in:Active,Suspended,Graduated,Dropped',
        ];
    }
}
