<?php

namespace App\Http\Requests\Faculty;

use Illuminate\Foundation\Http\FormRequest;

class FacultyRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string',
        ];
    }


    protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('faculty'),
        ]);
    }
}


