<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $username = (string) $this->username;
        $publicUsername = str_starts_with($username, 'teacher-profile-') ? null : $this->username;

        return [
            // identity
            'id'             => $this->id,
            'teacher_id'     => $this->teacher_id,
            // personal
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'full_name'      => $this->full_name,
            'gender'         => $this->gender,
            'dob'            => optional($this->dob)->toDateString(),
            'nationality'    => $this->nationality,
            'religion'       => $this->religion,
            'marital_status' => $this->marital_status,
            'national_id'    => $this->national_id,
            // contact
            'email'          => $this->email,
            'username'       => $publicUsername,
            'phone_number'   => $this->phone_number,
            'telegram'       => $this->telegram,
            'address'        => $this->address,
            'emergency_name' => $this->emergency_name,
            'emergency_phone'=> $this->emergency_phone,
            // professional
            'position'       => $this->position,
            'degree'         => $this->degree,
            'specialization' => $this->specialization,
            'contract_type'  => $this->contract_type,
            'salary_type'    => $this->salary_type,
            'salary'         => $this->salary,
            'experience'     => $this->experience,
            'join_date'      => optional($this->join_date)->toDateString(),
            'note'           => $this->note,
            // photo & documents
            'image'          => $this->image,
            'image_url'      => $this->image ?: null,
            'cv_file'        => $this->cv_file ?: null,
            'id_card_file'   => $this->id_card_file ?: null,
            // auth
            'role'           => $this->role,
            'status'         => $this->status ?: 'active',
            'deleted_at'     => optional($this->deleted_at)->toDateTimeString(),
            'deleted_by'     => $this->deleted_by,
            'delete_reason'  => $this->delete_reason,
            // relations
            'major'   => $this->major ? [
                'id'   => $this->major->id,
                'name' => $this->major->name,
            ] : null,
            'subject' => $this->subject ? [
                'id'   => $this->subject->id,
                'code' => $this->subject->subject_Code,
                'name' => $this->subject->name,
            ] : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
