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
            'permission_ids' => ['nullable', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'mode' => ['nullable', 'in:add,sync'],
        ];
    }
}


