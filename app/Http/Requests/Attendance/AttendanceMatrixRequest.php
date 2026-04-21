<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttendanceMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'session_date' => 'required|date',
            'session_count' => 'nullable|integer|min:1|max:60',
            'batch_year' => 'nullable',
            'year_level' => 'nullable',
            'semester' => 'nullable',
            'academic_year' => 'nullable|string|max:20',
            'faculty_id' => 'nullable|integer|exists:faculties,id',
            'major_id' => 'nullable|integer|exists:majors,id',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'study_day' => 'nullable|string|max:100',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required',
            'records.*.attendance' => 'required|array|min:1|max:60',
            'records.*.attendance.*' => 'nullable|in:Att,P,A,L,Present,Absent,Late,Excused',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'data' => [
                'errors' => $validator->errors(),
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'api_version' => 'v1',
            ],
        ], 422));
    }
}
