<?php

namespace App\Http\Requests\Shift;

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


