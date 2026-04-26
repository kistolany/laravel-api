<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\RbacPermissionCatalog;

class RoleAssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $knownPermissionNames = RbacPermissionCatalog::all();

        return [
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($knownPermissionNames)],
            'mode' => ['nullable', 'in:add,sync'],
        ];
    }
}


