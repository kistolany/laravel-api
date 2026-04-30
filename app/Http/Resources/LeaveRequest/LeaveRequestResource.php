<?php

namespace App\Http\Resources\LeaveRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'requester_type' => data_get($this->resource, 'requester_type'),
            'requester_id' => data_get($this->resource, 'requester_id'),
            'requester_name' => data_get($this->resource, 'requester_name'),
            'requester_name_kh' => data_get($this->resource, 'requester_name_kh'),
            'leave_type' => data_get($this->resource, 'leave_type'),
            'start_date' => data_get($this->resource, 'start_date'),
            'end_date' => data_get($this->resource, 'end_date'),
            'days' => data_get($this->resource, 'days'),
            'reason' => data_get($this->resource, 'reason'),
            'status' => data_get($this->resource, 'status'),
            'major_name' => data_get($this->resource, 'major_name'),
            'year' => data_get($this->resource, 'year'),
            'batch_year' => data_get($this->resource, 'batch_year'),
            'subject_name' => data_get($this->resource, 'subject_name'),
            'position' => data_get($this->resource, 'position'),
            'teacher_role' => data_get($this->resource, 'teacher_role'),
            'teacher_code' => data_get($this->resource, 'teacher_code'),
            'created_at' => data_get($this->resource, 'created_at'),
            'updated_at' => data_get($this->resource, 'updated_at'),
        ];
    }
}
