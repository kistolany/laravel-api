<?php

namespace App\Http\Requests\Api\V1;

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
            // The user selects these from a dropdown
            'major_id'   => 'required|exists:majors,id',
            'subject_id' => 'required|exists:subjects,id',
            
            // The user selects these numbers
            'year_level' => 'required|integer|between:1,4',
            'semester'   => 'required|integer|in:1,2',
        ];
    }
}