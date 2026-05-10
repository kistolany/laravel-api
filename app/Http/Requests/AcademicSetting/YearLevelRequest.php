<?php

namespace App\Http\Requests\AcademicSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class YearLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('year_level') ?? $this->route('yearLevel') ?? $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('year_levels', 'name')->ignore($id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('year_levels', 'code')->ignore($id),
            ],
            'number' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
                Rule::unique('year_levels', 'number')->ignore($id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
