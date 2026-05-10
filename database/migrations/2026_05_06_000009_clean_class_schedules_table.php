<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table): void {
            $table->dropColumn([
                'room',
                'academic_year',
                'year_level',
                'semester',
                'total_male',
                'total_female',
                'total_student',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table): void {
            $table->string('room', 100)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->unsignedSmallInteger('total_male')->default(0);
            $table->unsignedSmallInteger('total_female')->default(0);
            $table->unsignedSmallInteger('total_student')->default(0);
        });
    }
};
