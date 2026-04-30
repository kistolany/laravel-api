<?php

namespace App\Http\Requests\SubjectClassroom;

use Illuminate\Foundation\Http\FormRequest;

class SubjectClassroomOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
