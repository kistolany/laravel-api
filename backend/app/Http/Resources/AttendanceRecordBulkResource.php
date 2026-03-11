<?php

namespace App\Http\Resources;

use App\DTOs\AttendanceRecordBulkResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordBulkResource extends JsonResource
{
    /** @var AttendanceRecordBulkResult */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'attendance_session_id' => $this->resource->attendance_session_id,
            'total_records' => $this->resource->total_records,
        ];
    }
}
