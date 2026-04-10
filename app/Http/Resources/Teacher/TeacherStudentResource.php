<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_code' => 'ST' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT),
            'barcode' => $this->barcode,
            'full_name_kh' => $this->full_name_kh,
            'full_name_en' => $this->full_name_en,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'id_card_number' => $this->id_card_number,
            'major' => $this->academicInfo?->major ? [
                'id' => $this->academicInfo->major->id,
                'name_en' => $this->academicInfo->major->name_eg,
                'name_kh' => $this->academicInfo->major->name_kh,
            ] : null,
            'shift' => $this->academicInfo?->shift ? [
                'id' => $this->academicInfo->shift->id,
                'name_en' => $this->academicInfo->shift->name_en,
                'name_kh' => $this->academicInfo->shift->name_kh,
            ] : null,
            'batch_year' => $this->academicInfo?->batch_year,
            'stage' => $this->academicInfo?->stage,
            'study_days' => $this->academicInfo?->study_days,
            'class_status' => $this->pivot?->status,
            'joined_date' => $this->pivot?->joined_date,
        ];
    }
}


