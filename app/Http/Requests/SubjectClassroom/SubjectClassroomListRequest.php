<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class SubjectClassroomListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'nullable|integer|exists:classes,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'type' => 'nullable|in:homework,assignment,midterm,final',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'size' => 'nullable|integer|min:1|max:200',
        ];
    }
}
