<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class HomeworkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'class_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:classes,id'],
            'subject_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:subjects,id'],
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'assessment_type' => 'nullable|in:homework,assignment,midterm,final',
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'due_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'max_score' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'attachment' => 'nullable|file|max:10240',
        ];
    }
}
