<?php

namespace App\Http\Requests\Auth;

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
}

