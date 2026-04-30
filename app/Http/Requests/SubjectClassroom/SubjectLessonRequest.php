<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class SubjectLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'required|integer|exists:classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_date' => 'nullable|date',
            'file' => 'required|file|max:10240',
        ];
    }
}
