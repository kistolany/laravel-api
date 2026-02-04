<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'subject_Code'=>'sometimes|string',
            'name_kh' => 'sometimes|string',
            'name_eg' => 'sometimes|string',
        ];
    }

}
