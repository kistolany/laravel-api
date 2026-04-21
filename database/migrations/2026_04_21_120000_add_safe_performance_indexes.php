<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index(['student_type', 'status', 'created_at'], 'students_type_status_created_idx');
            $table->index(['status', 'created_at'], 'students_status_created_idx');
        });

        Schema::table('academic_info', function (Blueprint $table) {
            $table->index(['major_id', 'shift_id', 'batch_year', 'stage', 'study_days'], 'academic_info_student_filters_idx');
            $table->index('stage', 'academic_info_stage_idx');
            $table->index('study_days', 'academic_info_study_days_idx');
        });

        Schema::table('class_programs', function (Blueprint $table) {
            $table->index(['major_id', 'shift_id', 'year_level', 'semester', 'class_id'], 'class_programs_lookup_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index('academic_year', 'classes_academic_year_idx');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->index(['day_of_week', 'shift_id'], 'class_schedules_day_shift_idx');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index(['class_id', 'major_id', 'shift_id', 'academic_year', 'year_level', 'semester', 'id'], 'attendance_sessions_filter_idx');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['student_id', 'attendance_session_id', 'status'], 'attendance_records_student_session_status_idx');
        });

        Schema::table('student_scores', function (Blueprint $table) {
            $table->index(['class_id', 'subject_id', 'academic_year', 'year_level', 'semester', 'student_id'], 'student_scores_filter_idx');
            $table->index('academic_year', 'student_scores_academic_year_idx');
        });

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->index(['attendance_date', 'teacher_id', 'status'], 'teacher_attendance_date_teacher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropIndex('teacher_attendance_date_teacher_status_idx');
        });

        Schema::table('student_scores', function (Blueprint $table) {
            $table->dropIndex('student_scores_academic_year_idx');
            $table->dropIndex('student_scores_filter_idx');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_student_session_status_idx');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('attendance_sessions_filter_idx');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropIndex('class_schedules_day_shift_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_academic_year_idx');
        });

        Schema::table('class_programs', function (Blueprint $table) {
            $table->dropIndex('class_programs_lookup_idx');
        });

        Schema::table('academic_info', function (Blueprint $table) {
            $table->dropIndex('academic_info_study_days_idx');
            $table->dropIndex('academic_info_stage_idx');
            $table->dropIndex('academic_info_student_filters_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_status_created_idx');
            $table->dropIndex('students_type_status_created_idx');
        });
    }
};
