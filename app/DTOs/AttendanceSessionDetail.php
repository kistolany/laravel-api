<?php

namespace App\DTOs;

use App\Models\AttendanceSession;

class AttendanceSessionDetail
{
    public function __construct(
        public AttendanceSession $session,
        public array $students,
        public array $summary
    ) {}
}
