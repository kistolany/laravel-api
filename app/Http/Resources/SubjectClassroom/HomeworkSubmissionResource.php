<?php

namespace App\Http\Resources\SubjectClassroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'homework_id' => $this->homework_id,
            'student_id' => $this->student_id,
            'student_name' => $this->student?->full_name_en ?? $this->student?->full_name_kh,
            'student_name_kh' => $this->student?->full_name_kh,
            'id_card_number' => $this->student?->id_card_number,
            'file_url' => $this->file_url,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'note' => $this->note,
            'submitted_at' => $this->submitted_at,
            'is_late' => $this->is_late,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
