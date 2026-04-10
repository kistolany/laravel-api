<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttendanceRecordBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => 'required|exists:subjects,id',
            'session_date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required',
            'records.*.status' => 'required|in:Present,Absent,Late,Excused',
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


