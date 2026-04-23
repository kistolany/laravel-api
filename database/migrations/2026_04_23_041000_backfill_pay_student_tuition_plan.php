<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('students', 'tuition_plan')) {
            return;
        }

        DB::table('students')
            ->where('student_type', 'PAY')
            ->whereNull('tuition_plan')
            ->update([
                'tuition_plan' => 'PAY_FULL',
                'tuition_plan_assigned_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('students', 'tuition_plan')) {
            return;
        }

        DB::table('students')
            ->where('student_type', 'PAY')
            ->where('tuition_plan', 'PAY_FULL')
            ->update([
                'tuition_plan' => null,
                'tuition_plan_assigned_at' => null,
            ]);
    }
};
