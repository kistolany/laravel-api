<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table): void {
            if (!Schema::hasColumn('class_schedules', 'class_program_id')) {
                $table->foreignId('class_program_id')
                    ->nullable()
                    ->after('class_id')
                    ->constrained('class_programs')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('class_schedules', 'academic_year')) {
                $table->string('academic_year', 20)->nullable()->after('day_of_week');
            }
            if (!Schema::hasColumn('class_schedules', 'year_level')) {
                $table->unsignedTinyInteger('year_level')->nullable()->after('academic_year');
            }
            if (!Schema::hasColumn('class_schedules', 'semester')) {
                $table->unsignedTinyInteger('semester')->nullable()->after('year_level');
            }
        });

        DB::statement("
            UPDATE class_schedules cs
            JOIN class_programs cp ON cp.class_id = cs.class_id
            SET cs.class_program_id = cp.id
            WHERE cs.class_program_id IS NULL
              AND (cs.shift_id IS NULL OR cp.shift_id IS NULL OR cp.shift_id = cs.shift_id)
              AND (cs.year_level IS NULL OR cp.year_level IS NULL OR cp.year_level = cs.year_level)
              AND (cs.semester IS NULL OR cp.semester IS NULL OR cp.semester = cs.semester)
              AND (cs.academic_year IS NULL OR cp.academic_year IS NULL OR cp.academic_year = cs.academic_year)
        ");
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('class_schedules', 'class_program_id')) {
                $table->dropConstrainedForeignId('class_program_id');
            }
        });
    }
};
