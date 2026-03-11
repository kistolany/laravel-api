<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'email' => $this->email,
            'username' => $this->username,
            'phone_number' => $this->phone_number,
            'telegram' => $this->telegram,
            'address' => $this->address,
            'image' => $this->image,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'role' => $this->role,
            'is_verified' => (bool) $this->is_verified,
            'verified_at' => optional($this->verified_at)->toDateTimeString(),
            'major' => $this->major ? [
                'id' => $this->major->id,
                'name_en' => $this->major->name_eg,
                'name_kh' => $this->major->name_kh,
            ] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->id,
                'code' => $this->subject->subject_Code,
                'name_en' => $this->subject->name_eg,
                'name_kh' => $this->subject->name_kh,
            ] : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
