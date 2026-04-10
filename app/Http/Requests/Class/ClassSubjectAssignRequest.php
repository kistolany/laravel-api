<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;

class ClassSubjectAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'name_eg' => 'required_without:subject_id|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'subject_Code' => 'nullable|string|max:50',
        ];
    }
}


