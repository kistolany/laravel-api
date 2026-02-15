<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name_kh' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'time_range' => 'nullable|string',
        ];
    }
}
