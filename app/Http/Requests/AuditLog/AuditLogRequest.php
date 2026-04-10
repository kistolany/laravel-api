<?php

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
            'store' => $this->storeRules(),
            'destroy' => $this->destroyRules(),
            default => [],
        };
    }

    private function indexRules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ];
    }

    private function storeRules(): array
    {
        return [
            'action' => 'required|string|max:80',
            'module' => 'required|string|max:120',
            'description' => 'required|string|max:5000',
            'before' => 'nullable|string',
            'after' => 'nullable|string',
        ];
    }

    private function destroyRules(): array
    {
        return [
            'ids' => 'nullable|array|min:1',
            'ids.*' => 'integer|min:1',
            'clear_all' => 'nullable|boolean',
        ];
    }
}

