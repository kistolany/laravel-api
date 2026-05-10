<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class RoleAssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
            'mode' => ['nullable', 'in:add,sync'],
        ];
    }
}

