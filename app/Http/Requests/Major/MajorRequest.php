<?php

namespace App\Http\Requests\Major;

use Illuminate\Foundation\Http\FormRequest;

class MajorRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'faculty_id'=>'sometimes|integer',
            'name_kh' => 'sometimes|nullable|string',
            'name_eg' => 'sometimes|string',
        ];
    }

}


