<?php

namespace App\Http\Requests\AcademicSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('academic_term') ?? $this->route('academicTerm') ?? $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('academic_terms', 'name')->ignore($id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('academic_terms', 'code')->ignore($id),
            ],
            'number' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
                Rule::unique('academic_terms', 'number')->ignore($id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
