<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_info', function (Blueprint $table) {
            $table->index('student_id', 'academic_info_student_id_idx');
            $table->index('major_id', 'academic_info_major_id_idx');
            $table->index('shift_id', 'academic_info_shift_id_idx');
            $table->index('batch_year', 'academic_info_batch_year_idx');
        });

        Schema::table('major_subjects', function (Blueprint $table) {
            $table->index(
                ['major_id', 'subject_id', 'year_level', 'semester'],
                'major_subjects_lookup_idx'
            );
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index(
                ['teacher_id', 'session_date', 'session_number', 'id'],
                'attendance_sessions_teacher_timeline_idx'
            );
            $table->index(
                ['major_id', 'subject_id', 'session_date', 'session_number', 'id'],
                'attendance_sessions_major_subject_timeline_idx'
            );
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index(
                ['major_id', 'shift_id', 'academic_year', 'year_level', 'semester', 'is_active'],
                'classes_lookup_idx'
            );
        });

        Schema::table('class_students', function (Blueprint $table) {
            $table->index('student_id', 'class_students_student_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_students', function (Blueprint $table) {
            $table->dropIndex('class_students_student_id_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_lookup_idx');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('attendance_sessions_teacher_timeline_idx');
            $table->dropIndex('attendance_sessions_major_subject_timeline_idx');
        });

        Schema::table('major_subjects', function (Blueprint $table) {
            $table->dropIndex('major_subjects_lookup_idx');
        });

        Schema::table('academic_info', function (Blueprint $table) {
            $table->dropIndex('academic_info_student_id_idx');
            $table->dropIndex('academic_info_major_id_idx');
            $table->dropIndex('academic_info_shift_id_idx');
            $table->dropIndex('academic_info_batch_year_idx');
        });
    }
};
