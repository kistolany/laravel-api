<?php

namespace App\Http\Requests\Score;

use Illuminate\Foundation\Http\FormRequest;

class StudentScoreBulkUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scores' => 'required|array|min:1',
            'scores.*.student_id' => 'required',
            'scores.*.class_id' => 'nullable|integer|exists:classes,id',
            'scores.*.subject_id' => 'nullable|integer|exists:subjects,id',
            'scores.*.academic_year' => 'nullable|string|max:20',
            'scores.*.year_level' => 'nullable|string|max:20',
            'scores.*.semester' => 'nullable|string|max:20',
            'scores.*.class_score' => 'nullable|numeric|min:0|max:10',
            'scores.*.assignment_score' => 'nullable|numeric|min:0|max:10',
            'scores.*.midterm_score' => 'nullable|numeric|min:0|max:20',
            'scores.*.final_score' => 'nullable|numeric|min:0|max:50',
        ];
    }
}
