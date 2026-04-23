<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'student_id' => $this->student_id,
            'teacher_id' => $this->teacher_id,
            'staff_id' => $this->staff_id,
            'account_purpose' => $this->account_purpose,
            'linked_identity' => $this->linkedIdentity(),
            'full_name' => $this->full_name,
            'username' => $this->username,
            'phone' => $this->phone,
            'image' => $this->image,
            'status' => $this->status,
            'role' => $this->role?->name,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function linkedIdentity(): ?array
    {
        if ($this->teacher_id) {
            return [
                'type' => 'teacher',
                'id' => $this->teacher_id,
                'code' => $this->teacher?->teacher_id ?: "T-{$this->teacher_id}",
                'name' => $this->teacher?->full_name,
            ];
        }

        if ($this->student_id) {
            return [
                'type' => 'student',
                'id' => $this->student_id,
                'code' => $this->student?->barcode ?: "STU-{$this->student_id}",
                'name' => $this->student?->full_name_en ?: $this->student?->full_name_kh,
            ];
        }

        if ($this->staff_id) {
            return [
                'type' => 'staff',
                'id' => $this->staff_id,
                'code' => $this->staff_id,
                'name' => $this->full_name,
            ];
        }

        return null;
    }
}


