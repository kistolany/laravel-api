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
            'uploadImage' => $this->uploadImageRules(),
            'verifyOtp' => $this->verifyOtpRules(),
            'resendOtp' => $this->resendOtpRules(),
            'login' => $this->loginRules(),
            'refresh' => $this->refreshRules(),
            'logout' => $this->logoutRules(),
            'revoke' => $this->revokeRules(),
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
            'subject_id'      => 'required|integer|exists:subjects,id',
            'email'           => ['required', 'email', 'max:255', Rule::unique('teachers', 'email')],
            'username'        => ['required', 'string', 'max:255', Rule::unique('teachers', 'username')],
            'password'        => 'required|string|min:6|max:255',
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
        ];
    }

    private function uploadImageRules(): array
    {
        return [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    private function verifyOtpRules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'otp_code' => 'required|digits:6',
        ];
    }

    private function resendOtpRules(): array
    {
        return [
            'email' => 'required|email|max:255',
        ];
    }

    private function loginRules(): array
    {
        return [
            'login' => 'required|string|max:255',
            'password' => 'required|string|max:255',
        ];
    }

    private function refreshRules(): array
    {
        return [
            'refresh_token' => 'required|string',
        ];
    }

    private function logoutRules(): array
    {
        return [
            'refresh_token' => 'nullable|string',
        ];
    }

    private function revokeRules(): array
    {
        return [
            'refresh_token' => 'required|string',
        ];
    }
}

