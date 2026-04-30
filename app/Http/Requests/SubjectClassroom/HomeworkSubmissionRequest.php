<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class HomeworkSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'nullable|integer|exists:students,id',
            'file' => 'required|file|max:10240',
            'note' => 'nullable|string',
        ];
    }
}
