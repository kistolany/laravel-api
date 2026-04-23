<?php

namespace App\Http\Requests\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        $isScholarshipTrack = in_array(
            strtoupper((string) $this->input('student_type')),
            ['PENDING', 'PASS', 'FAIL'],
            true
        );

        return [
            // Student Table Fields
            'full_name_kh'      => 'required|string|max:255',
            'full_name_en'      => 'required|string|max:255',
            'gender'            => 'required|in:Male,Female,Other',
            'dob'               => 'required|date',
            'phone'             => 'nullable|string',
            'email'             => 'nullable|string',
            'id_card_number'    => [
                'nullable',
                'string',
                Rule::unique('students', 'id_card_number')->ignore(
                    $this->route('id') ?? $this->route('student')
                ),
            ],
            'image'                                 => 'nullable|string',
            'other_notes'       => 'nullable|string',
            'status'            => 'sometimes|in:enable,disable',
            'student_type'      => 'required|in:PAY,PENDING,PASS,FAIL',
            'tuition_plan'      => [
                'nullable',
                'string',
                Rule::in(['PAY_FULL', 'SCHOLARSHIP_FULL', 'SCHOLARSHIP_70', 'SCHOLARSHIP_50', 'SCHOLARSHIP_30']),
            ],
            'exam_place'        => 'nullable|string|max:255',
            'bacll_code'        => 'nullable|string|max:255',
            'grade'             => 'required|string|max:50',
            'doc'               => 'nullable|string',
            
            // Academic Info Table Fields
            'major_id'          => 'required|integer',
            'shift_id'          => 'required|integer',
            'batch_year'        => 'required|integer|min:2000',
            'stage'             => 'required|string',
            'study_days'        => 'required|string', 

            // Address Table Fields
            'addresses'                 => 'required|array|min:1',
            'addresses.*.address_type'  => 'required|in:Permanent,Current|distinct',
            'addresses.*.house_number'  => 'nullable|string|max:255',
            'addresses.*.street_number' => 'nullable|string|max:255',
            'addresses.*.village'       => 'nullable|string|max:255',
            'addresses.*.province_id'   => 'required|integer',
            'addresses.*.district_id'   => 'required|integer',
            'addresses.*.commune_id'    => 'required|integer',

            // Parent/Guardian Table Fields
            'parent_guardian'                => 'nullable|array',
            'parent_guardian.father_name'    => 'nullable|string|max:255',
            'parent_guardian.father_job'     => 'nullable|string|max:255',
            'parent_guardian.mother_name'    => 'nullable|string|max:255',
            'parent_guardian.mother_job'     => 'nullable|string|max:255',
            'parent_guardian.guardian_name'  => 'nullable|string|max:255',
            'parent_guardian.guardian_job'   => 'nullable|string|max:255',
            'parent_guardian.guardian_phone' => 'nullable|string|max:255',

            // Registration details
            'registration'                     => 'nullable|array',
            'registration.high_school_name'   => 'nullable|string|max:255',
            'registration.high_school_province' => 'nullable|string|max:255',
            'registration.bacii_exam_year'    => 'nullable|integer|min:2000|max:2100',
            'registration.bacii_grade'        => 'nullable|string|max:10',
            'registration.target_degree'      => 'nullable|string|max:255',
            'registration.diploma_attached'   => 'nullable|boolean',

            // Scholarship details
            'scholarship'                     => [
                Rule::requiredIf($isScholarshipTrack),
                'nullable',
                'array',
            ],
            'scholarship.nationality'         => 'nullable|string|max:255',
            'scholarship.ethnicity'           => 'nullable|string|max:255',
            'scholarship.emergency_name'      => [
                Rule::requiredIf($isScholarshipTrack),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_relation'  => [
                Rule::requiredIf($isScholarshipTrack),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_phone'     => [
                Rule::requiredIf($isScholarshipTrack),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_address'   => 'nullable|string',
            'scholarship.grade'               => 'nullable|string|max:10',
            'scholarship.exam_year'           => 'nullable|integer|min:2000|max:2100',
            'scholarship.guardians_address'   => 'nullable|string',
            'scholarship.guardians_phone_number' => 'nullable|string|max:255',
            'scholarship.guardians_email'     => 'nullable|email|max:255',
        ];
    }
}


