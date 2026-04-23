<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'register' => $this->registerRules(),
            'createUser' => $this->createUserRules(),
            'updateUser' => $this->updateUserRules(),
            'updateProfile' => $this->updateProfileRules(),
            'updateStatus' => $this->updateStatusRules(),
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
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6|max:255',
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
        ];
    }

    private function createUserRules(): array
    {
        return [
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'nullable|in:Active,Inactive',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'student_id' => 'nullable|integer|exists:students,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'staff_id' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'join_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'account_purpose' => 'nullable|string|in:Main Account,Assistant Account,Temporary Account',
            'image' => 'nullable|image|max:2048',
        ];
    }

    private function updateUserRules(): array
    {
        $id = (int) $this->route('id');

        return [
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($id),
            ],
            'password' => 'nullable|string|min:6|max:255',
            'role_id' => 'sometimes|required|integer|exists:roles,id',
            'status' => 'nullable|in:Active,Inactive',
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:255',
            'student_id' => 'nullable|integer|exists:students,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'staff_id' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'join_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:255',
            'account_purpose' => 'nullable|string|in:Main Account,Assistant Account,Temporary Account',
            'image' => 'nullable|image|max:2048',
        ];
    }

    private function updateProfileRules(): array
    {
        return [
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'new_password' => 'nullable|string|min:6|max:255|confirmed',
            'new_password_confirmation' => 'required_with:new_password|string|min:6|max:255',
        ];
    }

    private function updateStatusRules(): array
    {
        return [
            'status' => 'required|in:Active,Inactive',
        ];
    }

    private function loginRules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (!in_array($this->route()?->getActionMethod(), ['createUser', 'updateUser'], true)) {
                return;
            }

            $roleId = $this->input('role_id');
            if (!$roleId) {
                return;
            }

            $roleName = Role::whereKey($roleId)->value('name');
            $identityType = $this->identityTypeForRole($roleName);

            if ($identityType === 'teacher' && !$this->filled('teacher_id')) {
                $validator->errors()->add('teacher_id', 'Please link this user to a teacher.');
            }

            if ($identityType === 'student' && !$this->filled('student_id')) {
                $validator->errors()->add('student_id', 'Please link this user to a student.');
            }

        });
    }

    private function identityTypeForRole(?string $roleName): ?string
    {
        $normalized = strtolower(preg_replace('/\s+/', '', trim((string) $roleName)));

        return match ($normalized) {
            'teacher' => 'teacher',
            'student' => 'student',
            'staff', 'assistant', 'assistance', 'orderstaff' => 'staff',
            default => null,
        };
    }
}
