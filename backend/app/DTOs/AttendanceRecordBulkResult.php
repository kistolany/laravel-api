<?php

namespace App\DTOs;

class AttendanceRecordBulkResult
{
    public function __construct(
        public int $attendance_session_id,
        public int $total_records
    ) {}
}
