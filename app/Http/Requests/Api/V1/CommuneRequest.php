<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CommuneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_kh'     => 'sometimes|string|max:255',
            'name_en'     => 'sometimes|string|max:255',
            'district_id' => 'sometimes|exists:districts,id',
        ];
    }
    protected function prepareForValidation()
    {
        $this->merge(['id' => $this->route('commune')]); // Gets the {commune} ID from the URL
    }
}
