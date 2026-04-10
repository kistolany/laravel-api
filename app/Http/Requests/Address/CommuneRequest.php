<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class CommuneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'district_id' => 'sometimes|integer',
            'name' => 'sometimes|string',
        ];
    }
}


