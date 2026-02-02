<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FacultyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

   public function rules(): array
{
    return [
        // We validate the ID from the URL parameter
        'name_kh' => 'sometimes|string',
        'name_eg' => 'sometimes|string',
    ];
}

/**
 * We must add the ID from the URL to the validation data
 */


protected function prepareForValidation()
{
    $this->merge([
        'id' => $this->route('faculty'), // Gets the {faculty} ID from the URL
    ]);
}
}
