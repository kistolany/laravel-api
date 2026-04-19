<?php

namespace App\Http\Requests\Major;

use Illuminate\Foundation\Http\FormRequest;

class MajorRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'faculty_id' => 'sometimes|integer',
            'faculty'    => 'sometimes|string',  // faculty name (sent by frontend)
            'name'       => 'sometimes|required|string',
            'year'       => 'sometimes|integer',
            'shift'      => 'sometimes|string',
        ];
    }

}


