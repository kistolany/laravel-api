<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) ($this->route('id') ?? $this->route('permission'));

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($id)],
        ];
    }
}


