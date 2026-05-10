<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'      => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($id)],
            'building'  => 'nullable|string|max:100',
            'capacity'  => 'nullable|integer|min:1',
            'type'      => 'nullable|in:classroom,lab,hall,office',
            'note'      => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ];
    }
}
