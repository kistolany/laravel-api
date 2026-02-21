<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
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
            // Student reference
            'student_id'   => 'sometimes|string|max:255',
            
            // Physical address details
            'address_type' => 'sometimes|string|max:255',
            'house_number' => 'sometimes|string|max:255',
            'street_number'=> 'sometimes|string|max:255',
            'village'      => 'sometimes|string|max:255',
            
            // Geographical relationships (ensuring they exist)
            'province_id'  => 'sometimes|exists:provinces,id',
            'district_id'  => 'sometimes|exists:districts,id',
            'commune_id'   => 'sometimes|exists:communes,id',
        ];
    }
    protected function prepareForValidation()
    {
        $this->merge([
            'id' => $this->route('address'), // Gets the {address} ID from the URL
        ]);
    }
}
