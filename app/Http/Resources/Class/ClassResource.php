<?php

namespace App\Http\Resources\Class;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Class\ClassProgramResource;
use App\Http\Resources\Class\ClassStudentResource;

class ClassResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'is_active'     => $this->is_active,
            'programs'      => ClassProgramResource::collection($this->whenLoaded('programs')),
            'student_count' => $this->class_students_count ?? ($this->relationLoaded('students') ? $this->students->count() : $this->classStudents()->where('status', 'Active')->count()),
            'students'      => ClassStudentResource::collection($this->whenLoaded('students')),
        ];
    }
}
