<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name'         => 'sometimes|required|string',
            'subject_Code' => 'sometimes|nullable|string|max:50',
        ];
    }

}


