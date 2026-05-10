<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop session from class_schedules only if it exists
        if (Schema::hasColumn('class_schedules', 'session')) {
            Schema::table('class_schedules', function (Blueprint $table) {
                $table->dropColumn('session');
            });
        }

        // Fix unique key on teacher_attendances:
        //    Old: (teacher_id, attendance_date, session)  — wrong, ties attendance to teacher not schedule
        //    New: (schedule_id, attendance_date, session) — correct, each subject slot has Season 1 + Season 2
        //
        //    MySQL won't drop the old unique if teacher_id FK depends on it as the only index.
        //    So: add a plain index on teacher_id first, then drop old unique, then add new unique.
        DB::statement('CREATE INDEX teacher_attendances_teacher_id_idx ON teacher_attendances (teacher_id)');
        DB::statement('ALTER TABLE teacher_attendances DROP INDEX teacher_attendances_teacher_id_date_session_unique');
        DB::statement('ALTER TABLE teacher_attendances ADD UNIQUE teacher_attendances_schedule_date_season_unique (schedule_id, attendance_date, session)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_attendances DROP INDEX teacher_attendances_schedule_date_season_unique');
        DB::statement('ALTER TABLE teacher_attendances ADD UNIQUE teacher_attendances_teacher_id_date_session_unique (teacher_id, attendance_date, session)');

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->unsignedTinyInteger('session')->default(1)->after('shift_id');
        });
    }
};
