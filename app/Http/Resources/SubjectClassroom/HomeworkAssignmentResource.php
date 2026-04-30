<?php

namespace App\Http\Resources\SubjectClassroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkAssignmentResource extends JsonResource
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
            'assessment_type' => $this->assessment_type,
            'title' => $this->title,
            'description' => $this->description,
            'attachment_url' => $this->attachment_url,
            'attachment_name' => $this->attachment_name,
            'due_date' => $this->due_date,
            'max_score' => $this->max_score,
            'is_active' => $this->is_active,
            'is_overdue' => $this->is_overdue ?? null,
            'submissions_count' => $this->submissions_count ?? null,
            'unreviewed_submissions_count' => $this->unreviewed_submissions_count ?? null,
            'score_field' => $this->score_field ?? null,
            'my_submission' => $this->whenLoaded('my_submission'),
            'submission_state' => $this->submission_state ?? null,
            'can_submit_now' => $this->can_submit_now ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
