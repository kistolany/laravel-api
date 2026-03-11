<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female,Other',
            'major_id' => 'required|integer|exists:majors,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('teachers', 'email'),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teachers', 'username'),
            ],
            'password' => 'required|string|min:6|max:255',
            'phone_number' => 'nullable|string|max:50',
            'telegram' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'address' => 'required|string|max:2000',
        ];
    }
}
