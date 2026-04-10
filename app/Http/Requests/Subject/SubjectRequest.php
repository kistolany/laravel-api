<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name_kh' => 'sometimes|string',
            'name_eg' => 'sometimes|string',
        ];
    }

}


