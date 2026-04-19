<?php

namespace App\Http\Requests\Major;

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
            // Accept either major_id (int) or major (name string from frontend)
            'major_id'     => 'sometimes|integer|exists:majors,id',
            'major'        => 'sometimes|string',
            'faculty'      => 'sometimes|string',

            // Frontend sends 'year', API internally uses 'year_level'
            'year_level'   => 'sometimes|integer|between:1,5',
            'year'         => 'sometimes|integer|between:1,5',
            'semester'     => 'required|integer|in:1,2',

            'subject_id'   => 'nullable|integer|exists:subjects,id',
            'name'         => 'sometimes|string|max:255',
            'subject_Code' => 'nullable|string|max:50',
        ];
    }

}

