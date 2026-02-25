<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MajorSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'major_id'     => 'required|exists:majors,id',
            'year_level'   => 'required|integer|between:1,5',
            'semester'     => 'required|integer|in:1,2',

            // Pass even if subject_id is missing from JSON
            'subject_id'   => 'nullable|integer|exists:subjects,id',

            // If ID is missing, we need the name to create a new one
            'name_eg'      => 'required_without:subject_id|string|max:255',
            'name_kh'      => 'nullable|string|max:255',
            'subject_Code' => 'nullable|string|max:50',
        ];
    }

}