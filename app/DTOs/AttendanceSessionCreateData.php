<?php

namespace App\DTOs;

class AttendanceSessionCreateData
{
    public function __construct(
        public int $id,
        public int $class_id,
        public int $subject_id,
        public string $session_date,
        public int $session_number
    ) {}
}
