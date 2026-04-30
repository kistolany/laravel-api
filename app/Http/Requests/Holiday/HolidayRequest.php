<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'store' => $this->saveRules(),
            'update' => array_merge($this->saveRules(), [
                'remove_document' => 'nullable|boolean',
            ]),
            default => [],
        };
    }

    private function saveRules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:public,national,religious,school,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:500',
            'document' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:10240',
        ];
    }
}
