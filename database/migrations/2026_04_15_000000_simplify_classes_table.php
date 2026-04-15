<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['major_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn([
                'code',
                'major_id',
                'shift_id',
                'academic_year',
                'year_level',
                'semester',
                'section',
                'max_students',
                'is_active',
            ]);
            $table->string('name')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('code')->unique();
            $table->foreignId('major_id')->constrained('majors');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('academic_year');
            $table->unsignedTinyInteger('year_level');
            $table->unsignedTinyInteger('semester');
            $table->string('section', 10);
            $table->unsignedInteger('max_students')->default(0);
            $table->boolean('is_active')->default(true);
        });
    }
};
