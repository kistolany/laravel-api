<?php

use Illuminate\Database\Migrations\Migration;

// The unique index on teacher_attendances is already (schedule_id, attendance_date, session)
// as set by a prior migration. No schema change needed — this migration is a no-op placeholder.
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
