<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DistrictRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'province_id' => 'sometimes|integer',
            'name' => 'sometimes|string',
        ];
    }
}
