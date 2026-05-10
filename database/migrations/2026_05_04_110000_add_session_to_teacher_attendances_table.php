<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('session')->default(1)->after('attendance_date');
        });

        // Drop FK, drop old unique, re-add FK + new unique with session
        DB::statement('ALTER TABLE teacher_attendances DROP FOREIGN KEY teacher_attendances_teacher_id_foreign');
        DB::statement('ALTER TABLE teacher_attendances DROP INDEX teacher_attendances_teacher_id_attendance_date_unique');
        DB::statement('ALTER TABLE teacher_attendances ADD CONSTRAINT teacher_attendances_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE teacher_attendances ADD UNIQUE teacher_attendances_teacher_id_date_session_unique (teacher_id, attendance_date, session)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_attendances DROP FOREIGN KEY teacher_attendances_teacher_id_foreign');
        DB::statement('ALTER TABLE teacher_attendances DROP INDEX teacher_attendances_teacher_id_date_session_unique');
        DB::statement('ALTER TABLE teacher_attendances ADD CONSTRAINT teacher_attendances_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE teacher_attendances ADD UNIQUE teacher_attendances_teacher_id_attendance_date_unique (teacher_id, attendance_date)');

        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};
