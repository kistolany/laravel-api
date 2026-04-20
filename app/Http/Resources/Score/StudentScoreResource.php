<?php

namespace App\Http\Resources\Score;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'subject_id' => $this->subject_id,
            'academic_year' => $this->academic_year,
            'year_level' => $this->year_level,
            'semester' => $this->semester,
            'class_score' => (float) $this->class_score,
            'assignment_score' => (float) $this->assignment_score,
            'midterm_score' => (float) $this->midterm_score,
            'final_score' => (float) $this->final_score,
            'total' => (float) $this->total,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'barcode' => $this->student->barcode,
                'full_name_kh' => $this->student->full_name_kh,
                'full_name_en' => $this->student->full_name_en,
            ]),
            'class' => $this->whenLoaded('class', fn () => [
                'id' => $this->class?->id,
                'name' => $this->class?->name ?? $this->class?->code,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject?->id,
                'code' => $this->subject?->subject_Code,
                'name' => $this->subject?->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
