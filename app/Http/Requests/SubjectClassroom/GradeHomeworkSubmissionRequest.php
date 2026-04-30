<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class GradeHomeworkSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ];
    }
}
