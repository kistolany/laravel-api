<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\AcademicInfo\AcademicInfoResource;
use App\Http\Resources\Address\AddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    private const TUITION_PLAN_LABELS = [
        'PAY_FULL' => 'Pay Full',
        'SCHOLARSHIP_FULL' => 'Scholarship Full',
        'SCHOLARSHIP_70' => 'Scholarship 70%',
        'SCHOLARSHIP_50' => 'Scholarship 50%',
        'SCHOLARSHIP_30' => 'Scholarship 30%',
    ];

    private const SCHOLARSHIP_PERCENTAGES = [
        'SCHOLARSHIP_FULL' => 100,
        'SCHOLARSHIP_70' => 70,
        'SCHOLARSHIP_50' => 50,
        'SCHOLARSHIP_30' => 30,
    ];

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'barcode'           => $this->barcode,
            'full_name_kh'      => $this->full_name_kh,
            'full_name_en'      => $this->full_name_en,
            'gender'            => $this->gender,
            'dob'               => $this->dob,
            'phone' => $this->phone,
            'email' => $this->email,
            'id_card_number'    => $this->id_card_number,
            'student_type'      => $this->student_type,
            'tuition_plan'      => $this->tuition_plan,
            'tuition_plan_label' => self::TUITION_PLAN_LABELS[$this->tuition_plan] ?? null,
            'scholarship_percentage' => self::SCHOLARSHIP_PERCENTAGES[$this->tuition_plan] ?? null,
            'tuition_plan_assigned_at' => $this->tuition_plan_assigned_at?->format('Y-m-d H:i:s'),
            'exam_place'        => $this->exam_place,
            'bacll_code'        => $this->bacll_code,
            'grade'             => $this->grade,
            'doc'               => $this->doc,
            'image'             => $this->image,
            'status'            => $this->status,
            'deleted_at'        => $this->deleted_at?->format('Y-m-d H:i:s'),
            'deleted_by'        => $this->deleted_by,
            'delete_reason'     => $this->delete_reason,

            // 'whenLoaded' is great because it prevents unnecessary SQL queries
            'academic_details'  => $this->whenLoaded('academicInfo', fn() => new AcademicInfoResource($this->academicInfo)),
            'addresses'         => AddressResource::collection($this->whenLoaded('addresses')),
            'parent_guardian'   => new ParentGuardianResource($this->whenLoaded('parentGuardian')),
            'registration'      => new StudentRegistrationResource($this->whenLoaded('registration')),
            'scholarship'       => $this->whenLoaded('scholarship', function () {
                if (!$this->scholarship) {
                    return null;
                }

                return [
                    'id' => $this->scholarship->id,
                    'student_id' => $this->scholarship->student_id,
                    'nationality' => $this->scholarship->nationality,
                    'ethnicity' => $this->scholarship->ethnicity,
                    'emergency_name' => $this->scholarship->emergency_name,
                    'emergency_relation' => $this->scholarship->emergency_relation,
                    'emergency_phone' => $this->scholarship->emergency_phone,
                    'emergency_address' => $this->scholarship->emergency_address,
                    'grade' => $this->scholarship->grade,
                    'exam_year' => $this->scholarship->exam_year,
                    'guardians_address' => $this->scholarship->guardians_address,
                    'guardians_phone_number' => $this->scholarship->guardians_phone_number,
                    'guardians_email' => $this->scholarship->guardians_email,
                    'created_at' => $this->scholarship->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $this->scholarship->updated_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'classes'           => StudentClassResource::collection($this->whenLoaded('classes')),

            // Safety check: only format if the timestamp exists
            'registration_date'  => $this->registration_date,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


