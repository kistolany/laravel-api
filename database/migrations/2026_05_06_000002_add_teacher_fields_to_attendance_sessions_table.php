<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // teacher_id = the teacher originally scheduled to teach this subject
            if (!Schema::hasColumn('attendance_sessions', 'teacher_id')) {
                $table->foreignId('teacher_id')->nullable()->after('shift_id')
                    ->constrained('teachers')->nullOnDelete();
            }

            // actual_teacher_id = who actually taught the session (null = same as teacher_id)
            // Set when a replacement teacher covers the class.
            if (!Schema::hasColumn('attendance_sessions', 'actual_teacher_id')) {
                $table->foreignId('actual_teacher_id')->nullable()->after('teacher_id')
                    ->constrained('teachers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            foreach (['actual_teacher_id', 'teacher_id'] as $col) {
                if (Schema::hasColumn('attendance_sessions', $col)) {
                    $table->dropForeign([$col]);
                    $table->dropColumn($col);
                }
            }
        });
    }
};
