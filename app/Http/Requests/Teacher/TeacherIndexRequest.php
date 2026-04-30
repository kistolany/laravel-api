<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class TeacherIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archived' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,Active,Inactive',
        ];
    }
}
