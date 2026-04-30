<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class NotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'store' => $this->storeRules(),
            'feed' => $this->feedRules(),
            default => [],
        };
    }

    private function storeRules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:1000',
            'audience' => 'required|string|in:all,admin,teacher,staff',
            'priority' => 'required|string|in:normal,info,warning,urgent',
        ];
    }

    private function feedRules(): array
    {
        return [
            'since' => 'nullable|integer|min:1',
        ];
    }
}
