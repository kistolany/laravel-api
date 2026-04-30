<?php

namespace App\Http\Requests\LeaveRequest;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestRequest extends FormRequest
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
            'updateStatus' => $this->statusRules(),
            default => [],
        };
    }

    private function indexRules(): array
    {
        return [
            'requester_type' => 'nullable|string|in:student,teacher',
            'role' => 'nullable|string|max:80',
            'leave_type' => 'nullable|string|max:80',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    private function storeRules(): array
    {
        return [
            'requester_type' => 'nullable|string|in:student,teacher',
            'requester_id' => 'nullable|integer',
            'requester_name' => 'nullable|string|max:255',
            'requester_name_kh' => 'nullable|string|max:255',
            'leave_type' => 'required|string|max:80',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ];
    }

    private function statusRules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected,cancelled',
        ];
    }
}
