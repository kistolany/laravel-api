<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassBulkStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'major_id' => 'nullable|exists:majors,id',
            'joined_date' => 'nullable|date',
            'left_date' => 'nullable|date',
            'status' => 'nullable|in:Active,Suspended,Graduated,Dropped',
        ];
    }
}
