<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'code')->ignore($this->route('id') ?? $this->route('class')),
            ],
            'major_id' => 'required|exists:majors,id',
            'shift_id' => 'required|exists:shifts,id',
            'academic_year' => 'required|string|max:20',
            'year_level' => 'required|integer|min:1|max:8',
            'semester' => 'required|integer|min:1|max:3',
            'section' => 'required|string|max:10',
            'max_students' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
