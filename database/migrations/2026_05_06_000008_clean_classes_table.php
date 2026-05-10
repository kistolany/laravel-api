<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move missing fields from classes into class_programs before dropping
        DB::statement("
            INSERT INTO class_programs (class_id, major_id, shift_id, year_level, semester, created_at, updated_at)
            SELECT id, major_id, shift_id, year_level, semester, NOW(), NOW()
            FROM classes
            WHERE (major_id IS NOT NULL OR shift_id IS NOT NULL OR year_level IS NOT NULL OR semester IS NOT NULL)
              AND id NOT IN (SELECT DISTINCT class_id FROM class_programs)
        ");

        // Add missing columns to class_programs
        Schema::table('class_programs', function (Blueprint $table): void {
            $table->string('academic_year', 20)->nullable()->after('semester');
            $table->string('section', 50)->nullable()->after('academic_year');
            $table->unsignedInteger('max_students')->nullable()->after('section');
        });

        // Copy academic_year, section, max_students into existing class_programs rows
        DB::statement("
            UPDATE class_programs cp
            JOIN classes c ON c.id = cp.class_id
            SET cp.academic_year = c.academic_year,
                cp.section       = c.section,
                cp.max_students  = c.max_students
        ");

        // Drop the redundant columns from classes
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropForeign(['major_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn([
                'major_id',
                'shift_id',
                'academic_year',
                'year_level',
                'semester',
                'section',
                'max_students',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('section', 50)->nullable();
            $table->unsignedInteger('max_students')->nullable();
        });

        Schema::table('class_programs', function (Blueprint $table): void {
            $table->dropColumn(['academic_year', 'section', 'max_students']);
        });
    }
};
