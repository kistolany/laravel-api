<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DistrictRequest extends FormRequest
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
            'province_id' => 'required|exists:provinces,id',
            'name_kh' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            
        ];
    }
        protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('district'), // Gets the {district} ID from the URL
        ]);
    }

}
