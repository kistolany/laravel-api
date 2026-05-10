<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'register' => $this->registerRules(),
            'update' => $this->updateRules(),
            'uploadImage' => $this->uploadImageRules(),
            default => [],
        };
    }

    private function registerRules(): array
    {
        return [
            // required core
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'gender'          => 'required|in:Male,Female,Other',
            'major_id'        => 'required|integer|exists:majors,id',
            'subject_id'      => 'nullable|integer|exists:subjects,id',
            'email'           => ['required', 'email', 'max:255', Rule::unique('teachers', 'email')],
            'username'        => [
                'nullable',
                'required_with:password',
                'string',
                'max:255',
                Rule::unique('teachers', 'username'),
                Rule::unique('users', 'username'),
            ],
            'password'        => 'nullable|required_with:username|string|min:6|max:255',
            'role_id'         => 'nullable|integer|exists:roles,id',
            'address'         => 'required|string|max:2000',
            // optional personal
            'teacher_id'      => 'nullable|string|max:20',
            'dob'             => 'nullable|date',
            'nationality'     => 'nullable|string|max:100',
            'religion'        => 'nullable|string|max:100',
            'marital_status'  => 'nullable|in:single,married,divorced,widowed',
            'national_id'     => 'nullable|string|max:50',
            'phone_number'    => 'nullable|string|max:50',
            'telegram'        => 'nullable|string|max:255',
            'image'           => 'nullable',
            'cv_file'         => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'id_card_file'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            // optional emergency
            'emergency_name'  => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:50',
            // optional professional
            'position'        => 'nullable|string|max:50',
            'degree'          => 'nullable|in:bachelor,master,phd',
            'specialization'  => 'nullable|string|max:255',
            'contract_type'   => 'nullable|in:full-time,part-time,contract',
            'salary_type'     => 'nullable|in:monthly,hourly,per_class',
            'salary'          => 'nullable|numeric|min:0',
            'experience'      => 'nullable|integer|min:0',
            'join_date'       => 'nullable|date',
            'note'            => 'nullable|string|max:5000',
            'status'          => 'nullable|in:active,inactive,Active,Inactive,enable,disable',
        ];
    }

    private function updateRules(): array
    {
        $id = $this->route('id');

        return [
            // core
            'first_name'      => 'sometimes|required|string|max:255',
            'last_name'       => 'sometimes|required|string|max:255',
            'gender'          => 'sometimes|required|in:Male,Female,Other',
            'major_id'        => 'sometimes|required|integer|exists:majors,id',
            'subject_id'      => 'nullable|integer|exists:subjects,id',
            'email'           => ['sometimes', 'required', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($id)],
            'username'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teachers', 'username')->ignore($id)],
            'password'        => 'nullable|string|min:6|max:255',
            'address'         => 'sometimes|required|string|max:2000',
            // optional
            'teacher_id'      => 'nullable|string|max:20',
            'dob'             => 'nullable|date',
            'nationality'     => 'nullable|string|max:100',
            'religion'        => 'nullable|string|max:100',
            'marital_status'  => 'nullable|in:single,married,divorced,widowed',
            'national_id'     => 'nullable|string|max:50',
            'phone_number'    => 'nullable|string|max:50',
            'telegram'        => 'nullable|string|max:255',
            'image'           => 'nullable',
            'cv_file'         => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'id_card_file'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'emergency_name'  => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:50',
            'position'        => 'nullable|string|max:50',
            'degree'          => 'nullable|in:bachelor,master,phd',
            'specialization'  => 'nullable|string|max:255',
            'contract_type'   => 'nullable|in:full-time,part-time,contract',
            'salary_type'     => 'nullable|in:monthly,hourly,per_class',
            'salary'          => 'nullable|numeric|min:0',
            'experience'      => 'nullable|integer|min:0',
            'join_date'       => 'nullable|date',
            'note'            => 'nullable|string|max:5000',
            'status'          => 'nullable|in:active,inactive,Active,Inactive,enable,disable',
        ];
    }

    private function uploadImageRules(): array
    {
        return [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

}

