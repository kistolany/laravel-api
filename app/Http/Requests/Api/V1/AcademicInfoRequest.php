<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AcademicInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|string|max:255',
            'major_id' => 'required|exists:majors,id',
            'shift_id' => 'required|exists:shifts,id',
            'batch_year' => 'required|integer',
            'stage_id' => 'required|in:stage 1,stage 2,stage 3',
            'study_days' => 'required|string|max:255',
        ];
    }
}
