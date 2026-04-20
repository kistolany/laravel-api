<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete()->after('name');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete()->after('major_id');
            $table->string('academic_year', 20)->nullable()->after('shift_id');
            $table->unsignedTinyInteger('year_level')->nullable()->after('academic_year');
            $table->unsignedTinyInteger('semester')->nullable()->after('year_level');
            $table->string('section', 50)->nullable()->after('semester');
            $table->unsignedInteger('max_students')->nullable()->after('section');
            $table->boolean('is_active')->default(true)->after('max_students');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['major_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['major_id', 'shift_id', 'academic_year', 'year_level', 'semester', 'section', 'max_students', 'is_active']);
        });
    }
};
