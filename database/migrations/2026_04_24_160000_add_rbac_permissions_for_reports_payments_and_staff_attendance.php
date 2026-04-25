<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function permissions(): array
    {
        return [
            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',

            'student_payment.view',
            'student_payment.create',
            'student_payment.update',
            'student_payment.delete',
            'student_payment.export',

            'staff_attendance.view',
            'staff_attendance.create',
            'staff_attendance.update',
            'staff_attendance.report',
            'staff_attendance.export',

            'audit_log.delete',

            'report.export',
            'report_transcript.view',
            'report_student_progress.view',
            'report_class_performance.view',
            'report_attendance_summary.view',
            'report_financial.view',
            'report_enrollment_statistics.view',
            'report_gpa_standing.view',
            'report_teacher_workload.view',
            'report_graduation.view',
            'report_reexam.view',
        ];
    }

    public function up(): void
    {
        $rows = array_map(
            fn (string $name) => ['name' => $name],
            $this->permissions()
        );

        DB::table('permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', $this->permissions())
            ->delete();
    }
};
