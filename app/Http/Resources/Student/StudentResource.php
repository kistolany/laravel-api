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
            'tuition_plan_assigned_at' => $this->tuition_plan_assigned_at?->format('Y-m-d H:i:s'),
            'exam_place'        => $this->exam_place,
            'bacll_code'        => $this->bacll_code,
            'grade'             => $this->grade,
            'doc'               => $this->doc,
            'image'             => $this->image,
            'status'            => $this->status,

            // 'whenLoaded' is great because it prevents unnecessary SQL queries
            'academic_details'  => $this->whenLoaded('academicInfo', fn() => new AcademicInfoResource($this->academicInfo)),
            'addresses'         => AddressResource::collection($this->whenLoaded('addresses')),
            'parent_guardian'   => new ParentGuardianResource($this->whenLoaded('parentGuardian')),
            'registration'      => new StudentRegistrationResource($this->whenLoaded('registration')),
            'classes'           => StudentClassResource::collection($this->whenLoaded('classes')),

            // Safety check: only format if the timestamp exists
            'registration_date'  => $this->registration_date,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}


