<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: teacher_attendances already has schedule_id, session, replace_teacher_id,
// replace_status, and replace_subject_id in production. This migration is a no-op
// guard that ensures those columns and the correct unique index exist on fresh installs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_attendances', 'schedule_id')) {
                $table->foreignId('schedule_id')->nullable()->after('teacher_id')
                    ->constrained('class_schedules')->nullOnDelete();
            }

            if (!Schema::hasColumn('teacher_attendances', 'session')) {
                $table->unsignedTinyInteger('session')->default(1)->after('schedule_id');
            }

            if (!Schema::hasColumn('teacher_attendances', 'replace_teacher_id')) {
                $table->foreignId('replace_teacher_id')->nullable()->after('note')
                    ->constrained('teachers')->nullOnDelete();
            }

            if (!Schema::hasColumn('teacher_attendances', 'replace_status')) {
                $table->enum('replace_status', ['Present', 'Absent'])->nullable()->after('replace_teacher_id');
            }

            if (!Schema::hasColumn('teacher_attendances', 'replace_subject_id')) {
                $table->foreignId('replace_subject_id')->nullable()->after('replace_status')
                    ->constrained('subjects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            foreach (['replace_subject_id', 'replace_status', 'replace_teacher_id', 'session', 'schedule_id'] as $col) {
                if (Schema::hasColumn('teacher_attendances', $col)) {
                    if (in_array($col, ['schedule_id', 'replace_teacher_id', 'replace_subject_id'])) {
                        $table->dropForeign([$col]);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
