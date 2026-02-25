<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // Student Table Fields
            'full_name_kh'      => 'required|string|max:255',
            'full_name_en'      => 'required|string|max:255',
            'gender'            => 'required|in:Male,Female,Other',
            'dob'               => 'required|date',
            'phone'             => 'nullable|string',
            'email'             => 'nullable|string',
            'id_card_number'    => [
                'nullable',
                'string',
                Rule::unique('students', 'id_card_number')->ignore(
                    $this->route('id') ?? $this->route('student')
                ),
            ],
            'image'             => 'nullable|string',
            'other_notes'       => 'nullable|string',
            'status'            => 'sometimes|in:active,inactive',
            
            // Academic Info Table Fields
            'major_id'          => 'required|integer',
            'shift_id'          => 'required|integer',
            'batch_year'        => 'required|integer|min:2000',
            'stage'             => 'required|string',
            'study_days'        => 'required|string', 

            // Address Table Fields
            'addresses'                 => 'required|array|min:1',
            'addresses.*.address_type'  => 'required|in:Permanent,Current|distinct',
            'addresses.*.house_number'  => 'nullable|string|max:255',
            'addresses.*.street_number' => 'nullable|string|max:255',
            'addresses.*.village'       => 'nullable|string|max:255',
            'addresses.*.province_id'   => 'required|integer',
            'addresses.*.district_id'   => 'required|integer',
            'addresses.*.commune_id'    => 'required|integer',
        ];
    }
}
