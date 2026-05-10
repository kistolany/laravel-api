<?php

namespace App\Http\Resources\ClassSchedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'class_name' => $this->classroom?->name,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject?->name,
            'shift_id' => $this->shift_id,
            'shift_name' => $this->shift?->name,
            'shift_time' => $this->shift?->time_range,
            'day_of_week' => $this->day_of_week,
            'room_name' => $this->room,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->teacher ? trim($this->teacher->first_name . ' ' . $this->teacher->last_name) : null,
            'sent_by' => $this->sent_by,
            'sent_by_name' => $this->sentBy?->full_name ?? $this->sentBy?->username,
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
            'responded_at' => $this->responded_at?->toDateTimeString(),
            'schedule_id' => $this->schedule_id,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
