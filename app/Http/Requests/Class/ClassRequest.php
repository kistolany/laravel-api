<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;

class ClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'major_id'      => 'nullable|exists:majors,id',
            'shift_id'      => 'nullable|exists:shifts,id',
            'academic_year' => 'nullable|string|max:20',
            'year_level'    => 'nullable|integer|min:1|max:6',
            'semester'      => 'nullable|integer|min:1|max:2',
            'section'       => 'nullable|string|max:50',
            'max_students'  => 'nullable|integer|min:1',
            'is_active'     => 'nullable|boolean',
        ];
    }
}
