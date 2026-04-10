<?php

namespace App\Http\Requests\Faculty;

use Illuminate\Foundation\Http\FormRequest;

class FacultyRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            // We validate the ID from the URL parameter
                'name_kh' => 'sometimes|nullable|string',
            'name_eg' => 'sometimes|string',
        ];
    }


    protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('faculty'),
        ]);
    }
}


