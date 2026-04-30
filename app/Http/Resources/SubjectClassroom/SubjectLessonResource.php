<?php

namespace App\Http\Resources\SubjectClassroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'class_name' => $this->classroom?->name,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject?->name,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher ? trim($this->teacher->first_name . ' ' . $this->teacher->last_name) : null,
            'title' => $this->title,
            'description' => $this->description,
            'file_url' => $this->file_url,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'lesson_date' => $this->lesson_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
