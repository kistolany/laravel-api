<?php

namespace App\Http\Requests\AcademicSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('academic_year');

        return [
            'name'       => ['required', 'string', 'max:100', Rule::unique('academic_years', 'name')->ignore($id)],
            'status'     => ['nullable', 'in:active,upcoming,closed'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
